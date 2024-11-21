<?php

namespace SaveItems;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class main extends PluginBase {

	private Config $data;

	protected function onEnable(): void {
		if (!InvMenuHandler::isRegistered()) {
			InvMenuHandler::register($this);
		}

		@mkdir($this->getDataFolder());
		$this->data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		if (!$sender instanceof Player) {
			$sender->sendMessage("This command can only be used in-game.");
			return false;
		}

		if ($command->getName() === "savesitem") {
			$this->openSavedItemsMenu($sender);
			return true;
		}

		return false;
	}

	private function openSavedItemsMenu(Player $player): void {
		$menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST)
			->setName("Saved Items")
			->setInventoryCloseListener(function (Player $player, Inventory $inventory): void {
				$this->saveInventoryItems($player, $inventory);
			});

		$this->loadSavedItems($player, $menu->getInventory());
		$menu->send($player);
	}

	private function loadSavedItems(Player $player, Inventory $inventory): void {
		$savedItems = $this->data->get($player->getName(), []);
		foreach (array_map([$this, "decodeItem"], $savedItems) as $item) {
			$inventory->addItem($item);
		}
	}

	private function saveInventoryItems(Player $player, Inventory $inventory): void {
		$items = array_map([$this, "encodeItem"], array_filter($inventory->getContents(), fn(Item $item) => !$item->isNull()));
		$this->data->set($player->getName(), $items);
		$this->data->save();
		$player->sendMessage("Your items have been saved!");
	}

	private function encodeItem(Item $item): string {
		return base64_encode(gzcompress(serialize($item->nbtSerialize())));
	}

	private function decodeItem(string $data): Item {
		return Item::nbtDeserialize(unserialize(gzuncompress(base64_decode($data))));
	}
}
