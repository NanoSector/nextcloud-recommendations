<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Recommendations\Service;

use function array_map;
use function array_merge;
use function array_slice;
use function iterator_to_array;
use function usort;
use Generator;
use OC\Share\Constants;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUser;
use OCP\Share\IManager;
use OCP\Share\IShare;

class RecentlySharedFilesSource implements IRecommendationSource {

	/** @var IManager */
	private $shareManager;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IL10N */
	private $l10n;

	public function __construct(IManager $shareManager,
								IRootFolder $rootFolder,
								IL10N $l10n) {
		$this->shareManager = $shareManager;
		$this->rootFolder = $rootFolder;
		$this->l10n = $l10n;
	}

	/**
	 * @param IUser $user
	 * @param int $shareType
	 *
	 * @return Generator<IShare>
	 */
	private function getAllShares(IUser $user, int $shareType): Generator {
		$offset = 0;
		$pageSize = 50;

		while (count($page = $this->shareManager->getSharedWith(
			$user->getUID(),
			$shareType,
			null,
			$pageSize,
			$offset
		))) {
			foreach ($page as $share) {
				yield $share;
			}

			$offset += $pageSize;
		}
	}

	/**
	 * @param IShare[] $shares
	 *
	 * @return IShare[]
	 */
	private function sortShares(array $shares): array {
		usort($shares, function (IShare $a, IShare $b) {
			return $b->getShareTime()->getTimestamp() - $a->getShareTime()->getTimestamp();
		});
		return $shares;
	}

	/**
	 * @param IUser $user
	 *
	 * @todo load other share types as well
	 *
	 * @return IShare[]
	 */
	private function getMostRecentShares(IUser $user, int $max) {
		$shares = $this->sortShares(array_merge(
			iterator_to_array($this->getAllShares($user, Constants::SHARE_TYPE_USER)),
			iterator_to_array($this->getAllShares($user, Constants::SHARE_TYPE_GROUP))
		));

		return array_slice($shares, 0, $max);
	}

	/**
	 * @return IRecommendation[]
	 */
	public function getMostRecentRecommendation(IUser $user, int $max): array {
		$shares = $this->getMostRecentShares($user, $max);
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());

		return array_map(function (IShare $share) use ($userFolder) {
			return new RecommendedFile(
				$userFolder->getRelativePath($userFolder->getFullPath($share->getTarget())),
				$share->getNode(),
				$share->getShareTime()->getTimestamp(),
				$this->l10n->t("Recently shared")
			);
		}, $shares);
	}

}
