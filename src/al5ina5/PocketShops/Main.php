<?php

/**
 * The main file of the PocketShops plugin.
 * 
 * PHP Version 7
 *
 * @category CategoryName
 * @package  PackageName
 * @author   Sebastian Alsina <author@example.com>
 * @license  MIT http://underforums.com/
 * @link     http://underforums.com/
 */

declare(strict_types=1);

namespace al5ina5\PocketShops;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\permission\Permission;
use onebone\economyapi\EconomyAPI;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;

/**
 * Main
 * 
 * @category Plugin
 * @package  PocketShops
 * @author   Sebastian Alsina <alsinas@me.com>
 * @license  MIT http://underforums.com
 * @link     http://underforums.com
 */
class Main extends PluginBase implements Listener
{
    /**
     * An event fired when the plugin loads.
     *
     * @return void
     */
    public function onLoad()
    {
        $this->getLogger()->info(TextFormat::WHITE . "I've been loaded!");
        @mkdir($this->getDataFolder());
    }
    
    /**
     * An event fired when plugin is enabled.
     *
     * @return void
     */
    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    
        $defaultConfig = [
            "allow_in_creative" => false,
            "default_shop" => "shop",
            "lang" => [
                "onenable_message" => "Enabled",
                "ondisable_message" => "Disabled",
                "store_message" => "Hey! Each [item] will cost $[price]. How many would you like to purchase? We'll take yours off your hands for $[sell_price] a piece.",
                "not_enough_money" => "You do not have enough money to purchase this item.",
                "not_enough_items" => "You do not have enough of that item in your inventory to sell.",
                "not_enough_storage" => "Not enough space in your inventory to deposit your items. Free up some space and come back!",
                "you_purchased" => "You purchased [quantity] [item] for $[cost].",
                "you_sold" => "You sold [quantity] [item] for $[profit].",
                "no_permission" => "You do not have permission to access this shop.",
                "no_shops_defined" => "No shops are defined in the shops.yml file. Please define at least one shop and this message will disappear forever. Please refer to documentation for information on how to define shops.",
                "shop_not_found" => "That shop does not exist.",
                "now_allowed_in_creative" => "You can't open shops in creative."
            ],
            "plugin_info" => [
                "author" => "Sebastian Alsina",
                "github" => "https://github.com/al5ina5/PocketShops/",
                "documentation" => "https://github.com/al5ina5/PocketShops/wiki/",
                "please_check_out" => "https://underforums.com"
            ]
        ];
        
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, $defaultConfig);
        $this->shops = new Config($this->getDataFolder() . "shops.yml", Config::YAML);

        foreach ($this->shops as $shop => $info) {
            $perm = new Permission("shop.command.$shop", "Access to the '$shop' shop.");
            $this->getPlugin()->getServer()->getPluginManager()->addPermission($perm);
        }

        $this->getLogger()->info(TextFormat::YELLOW . TextFormat::BOLD . $this->config->get("lang")["onenable_message"]);
    }
    
    /**
     * An event fired when the plugin is disabled.
     *
     * @return void
     */
    public function onDisable()
    {
        $this->getLogger()->info(TextFormat::YELLOW . TextFormat::BOLD . $this->config->get("lang")["ondisable_message"]);
    }

    /**
     * An event fired when a player runs a command.
     *
     * @param CommandSender $sender  The sender.
     * @param Command       $command The command.
     * @param string        $label   The label.
     * @param array         $args    The arguments.
     * 
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, $label, array $args) : bool
    {
        switch($command->getName()){
        case "shop":
            if (!isset($args[0])) {
                $this->openShop($this->config->get("default_shop"), $sender->getPlayer());
                return true;
            }

            $this->openShop($args[0], $sender->getPlayer());
                
            return true;
        default:
            return true;
        }
    }
    
    /**
     * Reloads the plugin's .yml configuration files into memory.
     *
     * @return void
     */
    public function reloadShopAndConfig() : void
    {
        $this->shops = new Config($this->getDataFolder() . "shops.yml", Config::YAML);
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
    }
    
    /**
     * Opens a GUI that displays all the sections of a shop.
     *
     * @param string $shopName The name of the shop to open.
     * @param Player $player   The player object of the player.
     * 
     * @return void
     */
    public function openShop(string $shopName, Player $player)
    {
        $this->reloadShopAndConfig();

        if (count($this->shops->getAll()) <= 0) {
            $player->sendMessage($this->config->get("lang")["no_shops_defined"]);
            return true;
        }
        
        if (!isset($this->shops->getAll()[$shopName])) {
            $player->sendMessage($this->config->get("lang")["shop_not_found"]);
            return true;
        }

        if ($player->getGamemode() == Player::CREATIVE && !$this->config->get("allow_in_creative")) {
            $player->sendMessage($this->config->get("lang")["now_allowed_in_creative"]);
            return true;
        }

        $shop = $this->shops->getAll()[$shopName];

        if (!$player->hasPermission("pocketshops.command.$shopName")) {
            if ($this->config->get("lang")["no_permission"] != "") {
                $player->sendMessage($this->config->get("lang")["no_permission"]);
            }
            return true;
        };

        $form = new SimpleForm(
            function (Player $player, $data) use ($shop) {
                if ($data === null) {
                    return;
                }

                $this->openShopSection($shop, $data, $player);
            }
        );

        $form->setTitle($shop["name"]);
        foreach ($shop["sections"] as $section => $info) {
            $form->addButton($info["name"]);
        }
        $form->sendToPlayer($player);
    }
    
    /**
     * Opens a GUI that displays all the items of a shop's section.
     *
     * @param array  $shop      The targeted shop.
     * @param int    $sectionID The targeted section within that shop.
     * @param Player $player    The player.
     * 
     * @return void
     */
    public function openShopSection($shop, int $sectionID, Player $player)
    {
        $section = array_values($shop["sections"])[$sectionID];

        $form = new SimpleForm(
            function (Player $player, $data) use ($section) {
                if ($data === null) {
                    return;
                }

                $item = $this->parseShopItem($section["items"][$data]);

                if ($item["command"]) {
                    $this->purchaseCommand($item["command"], $item["price"], $player);
                    return;
                }

                $this->openItemOrder($section, $data, $player);
            }
        );

        $form->setTitle($section["name"]);
        foreach ($section["items"] as $item => $info) {
            $parse = $this->parseShopItem($info);
            $form->addButton($parse["custom_name"] . " $" . $parse["price"]);
        }
        $form->sendToPlayer($player);
    }
    
    /**
     * Open the item order screen where a user can enter the quantity
     * of an item and define wether they'd like to buy or sell.
     *
     * @param array  $section The targeted section.
     * @param int    $itemID  The item within that section.
     * @param Player $player  The player.
     * 
     * @return void
     */
    public function openItemOrder($section, int $itemID, $player)
    {
        $item = $this->parseShopItem($section["items"][$itemID]);

        $form = new CustomForm(
            function (Player $player, $data) use ($item) {
                if ($data === null) { 
                    return;
                }

                $quantity = 1;
                if ((int) $data[1] > 0) {
                    $quantity = (int) $data[1];
                }

                $selling = false;
                if ($data[2] == "1") {
                    $selling = true;
                }

                if ($selling) {
                    $this->sellItem($item, $quantity, $player);
                    return;
                }

                $this->purchaseItem($item, $quantity, $player);
            }
        );

        $form->setTitle($item["custom_name"]);
        $form->addLabel(
            str_replace(["[item]", "[price]", "[sell_price]"], [$item["custom_name"], $item["price"], $item["sell_price"]], $this->config->get("lang")["store_message"])
            // "Each " . $item["custom_name"] . " will cost you $" . $item["price"] . ".\n" .
            // "How many would you like to purchase?\n" .
            // "We'll buy yours for $" . $item["sell_price"] . " a piece.\n\n"
        );
        $form->addInput("Quantity", "1");
        $form->addToggle("Buy - Sell", false);
        $form->addLabel(" ");
        $form->sendToPlayer($player);
    }
        
    /**
     * Executes and command and reduces a player's money.
     *
     * @param string $command The command to run.
     * @param int    $price   The price of the command product.
     * @param Player $player  The player.
     * 
     * @return void
     */
    public function purchaseCommand($command, $price, $player)
    {
        if (EconomyAPI::getInstance()->myMoney($player) < $price) {
            $player->sendMessage($this->config->get("lang")["not_enough_money"]);
            return false;
        }

        $player->sendMessage("You bought a commmand.");
        $this->getServer()->dispatchCommand(new ConsoleCommandSender(), str_replace("@p", $player->getName(), $command));
        EconomyAPI::getInstance()->reduceMoney($player, $price);
    }
    
    /**
     * Removes an item from a player's inventory and increases the player's money.
     *
     * @param array  $item     An array containing the information of a shop item.
     * @param int    $quantity The quantity to sell.
     * @param Player $player   The player.
     * 
     * @return void
     */
    public function sellItem($item, $quantity, $player)
    {
        $itemObject = Item::get($item["id"], $item["dv"], $item["stack"] * $quantity);

        if (!$player->getInventory()->contains($itemObject)) {
            $player->sendMessage($this->config->get("lang")["not_enough_items"]);
            return;
        }

        $transactionValue = $item["sell_price"] * $quantity;

        $player->getInventory()->removeItem($itemObject);
        EconomyAPI::getInstance()->addMoney($player, $transactionValue);

        $player->sendMessage("You sold " . $item["stack"] * $quantity . " " . $item["name"] . " for " . $transactionValue . ".");
        $player->sendMessage(str_replace(["[quantity]", "[item]", "[profit]"], [$item["stack"] * $quantity, $item["custom_name"], $transactionValue], $this->config->get("lang")["you_sold"]));

    }

    /**
     * Adds an item to a player's inventory and reduces the player's money.
     *
     * @param array  $item     The item data.
     * @param int    $quantity The quantity.
     * @param Player $player   The Player object.
     * 
     * @return void
     */
    public function purchaseItem($item, $quantity, $player)
    {
        $itemObject = Item::get($item["id"], $item["dv"], $item["stack"] * $quantity);

        if ($item["custom_name"] != $item["name"]) {
            $itemObject->setCustomName($item["custom_name"]);
        }

        if (!$player->getInventory()->canAddItem($itemObject)) {
            $player->sendMessage($this->config->get("lang")["not_enough_storage"]);
            return;
        }

        $transactionValue = $item["price"] * $quantity;

        if (EconomyAPI::getInstance()->myMoney($player) < $transactionValue) {
            $player->sendMessage($this->config->get("lang")["not_enough_money"]);
            return;
        }

        $player->getInventory()->addItem($itemObject);
        EconomyAPI::getInstance()->reduceMoney($player, $transactionValue);

        
        $player->sendMessage(str_replace(["[quantity]", "[item]", "[cost]"], [$item["stack"] * $quantity, $item["custom_name"], $transactionValue], $this->config->get("lang")["you_purchased"]));
    }

    /**
     * Parse the item string into useable item information. 
     *
     * @param string $entry A string containing information on a shop item.
     * 
     * @return array
     */
    public function parseShopItem($entry)
    {
        $item = [];

        $item["command"] = false; // cmd:The Alleviator:12000:citem alleviator @p
        if (explode(":", $entry)[0] == "cmd") {
            $item["command"] = explode(":", $entry)[3];
            $item["price"] = (int) explode(":", $entry)[2];
            $item["name"] = explode(":", $entry)[1];
            $item["custom_name"] = explode(":", $entry)[1];
            return $item;
        } else {
            $item["id"] = (int) explode(":", $entry)[0];
            $item["dv"] = (int) explode(":", $entry)[1];
            $item["stack"] = (int) explode(":", $entry)[2];
            $item["price"] = (int) explode(":", $entry)[3];
            $item["sell_price"] = (int) explode(":", $entry)[4];
    
            if ($item["sell_price"] == -1) {
                $item["sell_price"] = $item["price"] * 0.6;
            }
    
            $item["name"] = Item::get((int) explode(":", $entry)[0], (int) explode(":", $entry)[1])->getName();
    
            if (isset(explode(":", $entry)[5])) {
                $item["custom_name"] = explode(":", $entry)[5];  
            } else {
                $item["custom_name"] = Item::get((int) explode(":", $entry)[0], (int) explode(":", $entry)[1])->getName();
            }

            return $item;
        }
    }
}
