<?php

namespace SaveItems;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class main extends PluginBase implements Listener{
	private static ?main $instance = null;
	private Config $data;

	public function onEnable() : void{
		@mkdir($this->getDataFolder());
		self::$instance = $this;
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
	}

	public static function getInstance() : ?main{
		return self::$instance;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		if (!$sender instanceof Player) {
			$sender->sendMessage("This command can only be used in-game.");
			return false;
		}
		switch ($command->getName()) {
			case "saveitems":
				$this->saveItem($sender);
				return true;
			case "takeitems":
				$this->getItem($sender);
				return true;
		}
		return false;
	}

	private function saveItem(Player $player): void {
		$inventory = $player->getInventory();
		$items = $inventory->getContents();

		if (empty($items)) {
			$player->sendMessage("Your inventory is empty.");
			return;
		}

		$playerName = $player->getName();
		$savedItems = [];

		foreach ($items as $slot => $item) {
			if (!$item->isNull()) {
				$savedItems[] = self::encodeItem($item);
				$inventory->setItem($slot, VanillaItems::air()); // Clear the item
			}
		}

		$this->data->set($playerName, $savedItems);
		$this->data->save();

		$player->sendMessage("All items in your inventory have been saved!");
	}

	private function getItem(Player $player): void {
		$playerName = $player->getName();

		if (!$this->data->exists($playerName) || empty($this->data->get($playerName))) {
			$player->sendMessage("You have no saved items.");
			return;
		}

		$savedItems = array_map([self::class, 'decodeItem'], $this->data->get($playerName));
		$inventory = $player->getInventory();

		foreach ($savedItems as $item) {
			if (!$inventory->canAddItem($item)) {
				$player->sendMessage("Not enough space in your inventory to retrieve saved items!");
				return;
			}
		}

		foreach ($savedItems as $item) {
			$inventory->addItem($item);
		}

		$this->data->remove($playerName);
		$this->data->save();

		$player->sendMessage("All saved items have been added back to your inventory!");
	}


	public static function encodeItem(Item $item) : string {
		$itemToJson = self::itemToJson($item);
		return base64_encode(gzcompress($itemToJson));
	}

	public static function decodeItem(string $item) : Item {
		$itemFromJson = gzuncompress(base64_decode($item));
		return self::jsonToItem($itemFromJson);
	}

	public static function itemToJson(Item $item) : string {
		$cloneItem = clone $item;
		$itemNBT = $cloneItem->nbtSerialize();
		return base64_encode(serialize($itemNBT));
	}

	public static function jsonToItem(string $json) : Item {
		$itemNBT = unserialize(base64_decode($json));
		return Item::nbtDeserialize($itemNBT);
	}
}