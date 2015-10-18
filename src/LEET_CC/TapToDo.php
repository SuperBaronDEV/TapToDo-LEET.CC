<?php
namespace LEET_CC;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;

use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\level\Position;

use pocketmine\Player;

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

    public $cmdSessions = [], $normalSessions = [];
    /** @var  Block[] */
    public $blocks;
    /** @var  Config */
    private $config;

    public function onEnable()
    {
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

        switch(strtolower($cmd->getName()))
        {
            case "action":

                if(!$sender instanceof Player)
                {
                    $sender->sendMessage("This command should not be run on console.");

                    return true;
                }

                $sender->sendMessage("Please write your command into chat, other players won't be able to see it!");

                $this->cmdSessions[$sender->getName()] = true;

                break;

            case "actionDelAll":

                break;

            default:

                break;
        }

        return true;
    }

    public function onInteract(PlayerInteractEvent $event)
    {
        // TODO: ...
    }

    public function onLevelLoad(LevelLoadEvent $event)
    {
        $this->getLogger()->info("Reloading blocks due to level " . $event->getLevel()->getName() . " loaded...");

        $this->parseBlockData();
    }

    public function onPlayerCommand(PlayerCommandPreprocessEvent $event)
    {
        $player = $event->getPlayer();

        if(isset($this->cmdSessions[$event->getPlayer()->getName()]))
        {
            $command = $event->getMessage();

            // TODO: ...

            $player->sendMessage("Added a new command to the block.");

            $event->setCancelled();
        }

        unset($this->cmdSessions[$event->getPlayer()->getName()]);
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
