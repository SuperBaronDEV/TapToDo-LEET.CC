<?php
namespace LEET_CC;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;

use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\level\Position;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\Config;

class TapToDo extends PluginBase implements CommandExecutor, Listener
{
    /*

         _|        _|_|_|_|  _|_|_|_|  _|_|_|_|_|       _|_|_|    _|_|_|
         _|        _|        _|            _|         _|        _|
         _|        _|_|_|    _|_|_|        _|         _|        _|
         _|        _|        _|            _|         _|        _|
         _|_|_|_|  _|_|_|_|  _|_|_|_|      _|     _|    _|_|_|    _|_|_|

         Original TapToDo plugin by Falkirks, Modified for LEET.CC by 64FF00

    */

    public $sessions;
    /** @var  Block[] */
    public $blocks;
    /** @var  Config */
    private $config;

    public function onEnable()
    {
        $this->sessions = [];

        $this->blocks = [];

        $this->saveDefaultConfig();

        $this->config = $this->getConfig();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->parseBlockData();
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args)
    {
        // TODO: Change command structure
        // TODO: - /action: Please write your command into chat, other players won't be able to see it!
        // TODO: - Use %player% to replace with the player name
        // TODO: - /actionDelAll: Remove all actions assigned to the block

        return true;
    }

    public function onInteract(PlayerInteractEvent $event)
    {
        if(isset($this->sessions[$event->getPlayer()->getName()]))
        {
            $args = $this->sessions[$event->getPlayer()->getName()];

            switch($args[0])
            {
                case "add":

                    if(isset($args[1]))
                    {
                        if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block)
                        {
                            array_shift($args);

                            $b->addCommand(implode(" ", $args));

                            $event->getPlayer()->sendMessage("Command added.");
                        }
                        else
                        {
                            array_shift($args);

                            $this->addBlock($event->getBlock(), implode(" ", $args));

                            $event->getPlayer()->sendMessage("Command added.");
                        }
                    }
                    else
                    {
                        $event->getPlayer()->sendMessage("You must specify a command.");
                    }

                    break;

                case "del":

                    if(isset($args[1]))
                    {
                        if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block)
                        {
                            array_shift($args);

                            if(($b->deleteCommand(implode(" ", $args))) !== false)
                            {
                                $event->getPlayer()->sendMessage("Command removed.");
                            }
                            else
                            {
                                $event->getPlayer()->sendMessage("Couldn't find command.");
                            }

                        }
                        else
                        {
                            $event->getPlayer()->sendMessage("Block does not exist.");
                        }
                    }
                    else
                    {
                        $event->getPlayer()->sendMessage("You must specify a command.");
                    }

                    break;

                case "delall":

                    if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block)
                    {
                        $this->deleteBlock($b);

                        $event->getPlayer()->sendMessage("Block deleted.");
                    }
                    else
                    {
                        $event->getPlayer()->sendMessage("Block doesn't exist.");
                    }

                    break;

                case "name":
                    if(isset($args[1]))
                    {
                        if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block)
                        {
                            $b->setName($args[1]);

                            $event->getPlayer()->sendMessage("Block named.");
                        }
                        else
                        {
                            $event->getPlayer()->sendMessage("Block doesn't exist.");
                        }
                    }
                    else
                    {
                        $event->getPlayer()->sendMessage("You need to specify a name.");
                    }

                    break;

                case "list":

                    if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block)
                    {
                        foreach($b->getCommands() as $cmd)
                        {
                            $event->getPlayer()->sendMessage($cmd);
                        }
                    }
                    else
                    {
                        $event->getPlayer()->sendMessage("Block doesn't exist.");
                    }

                    break;
            }

            unset($this->sessions[$event->getPlayer()->getName()]);
        }
        else
        {
            if(($b = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block && $event->getPlayer()->hasPermission("taptodo.tap"))
            {
                $b->executeCommands($event->getPlayer());
            }
        }
    }
    public function onLevelLoad(LevelLoadEvent $event)
    {
        $this->getLogger()->info("Reloading blocks due to level " . $event->getLevel()->getName() . " loaded...");

        $this->parseBlockData();
    }

    /**
     * @param $name
     * @return Block[]
     */
    public function getBlocksByName($name)
    {
        $ret = [];

        foreach($this->blocks as $block)
        {
            if($block->getName() === $name) $ret[] = $block;
        }

        return $ret;
    }

    /**
     * @param $x
     * @param $y
     * @param $z
     * @param $level
     * @return Block
     */
    public function getBlock($x, $y, $z, $level)
    {
        if($x instanceof Position) return (isset($this->blocks[$x->getX() . ":" . $x->getY() . ":" . $x->getZ() . ":" . $x->getLevel()->getName()]) ? $this->blocks[$x->getX() . ":" . $x->getY() . ":" . $x->getZ() . ":" . $x->getLevel()->getName()] : false);
        else return (isset($this->blocks[$x . ":" . $y . ":" . $z . ":" . $level]) ? $this->blocks[$x . ":" . $y . ":" . $z . ":" . $level] : false);
    }
    /**
     *
     */
    public function parseBlockData()
    {
        $this->blocks = [];

        foreach($this->config->get("blocks") as $i => $block)
        {
            if($this->getServer()->isLevelLoaded($block["level"]))
            {
                $pos = new Position($block["x"], $block["y"], $block["z"], $this->getServer()->getLevelByName($block["level"]));

                if(isset($block["name"])) $this->blocks[$pos->__toString()] = new Block($pos, $block["commands"], $this, $block["name"]);

                else $this->blocks[$block["x"] . ":" . $block["y"] . ":" . $block["z"] . ":" . $block["level"]] = new Block($pos, $block["commands"], $this, $i);
            }
            else
            {
                $this->getLogger()->warning("Could not load block in level " . $block["level"] . " because that level is not loaded.");
            }
        }
    }

    /**
     * @param Block $block
     */
    public function deleteBlock(Block $block)
    {
        $blocks = $this->config->get("blocks");

        unset($blocks[$block->id]);

        $this->config->set("blocks", $blocks);
        $this->config->save();

        $this->parseBlockData();
    }
    /**
     * @param Position $p
     * @param $cmd
     * @return Block
     */
    public function addBlock(Position $p, $cmd)
    {
        $block = new Block(new Position($p->getX(), $p->getY(), $p->getZ(), $p->getLevel()), [$cmd], $this, count($this->blocksConfig->get("blocks")));
        $this->saveBlock($block);

        $this->config->save();

        return $block;
    }

    /**
     * @param Block $block
     */
    public function saveBlock(Block $block)
    {
        $this->blocks[$block->getPosition()->getX() . ":" . $block->getPosition()->getY() . ":" . $block->getPosition()->getZ() . ":" . $block->getPosition()->getLevel()->getName()] = $block;

        $blocks = $this->config->get("blocks");
        $blocks[$block->id] = $block->toArray();

        $this->config->set("blocks", $blocks);
        $this->config->save();
    }
    /**
     *
     */
    public function onDisable()
    {
        $this->getLogger()->info("Saving blocks...");

        foreach($this->blocks as $block)
        {
            $this->saveBlock($block);
        }

        $this->config->save();
    }
}
