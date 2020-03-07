<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2020  onebone <me@onebone.me>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\internal\CurrencyReplacer;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;

class TopMoneyCommand extends PluginCommand {
	public function __construct(EconomyAPI $plugin) {
		$desc = $plugin->getCommandMessage("topmoney");
		parent::__construct("topmoney", $plugin);
		$this->setDescription($desc["description"]);
		$this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.topmoney");
	}

	public function execute(CommandSender $sender, string $label, array $params): bool {
		if(!$this->testPermission($sender)) return false;

		$page = max(1, (int) array_shift($params));

		/** @var EconomyAPI $plugin */
		$plugin = $this->getPlugin();

		$currency = $plugin->getPlayerPreferredCurrency($sender, false);

		$plugin->getSortByRange($currency, ($page - 1) * 5, 5)->then(function($value) use ($plugin, $currency, $sender, $page) {
			$sender->sendMessage($plugin->getMessage('topmoney-tag', $sender, [$page, '&enull&f'])); // TODO: show max page

			$i = 0;
			foreach($value as $player => $money) {
				$i++;
				$sender->sendMessage($plugin->getMessage('topmoney-format', $sender, [
					($page - 1) * 5 + $i, $player, new CurrencyReplacer($currency, $money)
				]));
			}
		})->catch(function() use ($sender) {
			$sender->sendMessage('Failed to fetch money leaderboard :(');
		});
		return true;
	}
}
