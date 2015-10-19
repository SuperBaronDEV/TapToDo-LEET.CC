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

                $sender->sendMessage("Please tap a block to assign your command.");

                $this->normalSessions[$sender->getName()] = 'action';

                break;

            case "actiondelall":

                if(!$sender instanceof Player)
                {
                    $sender->sendMessage("This command should not be run on console.");

                    return true;
                }

                $sender->sendMessage("Select the target block to continue.");

                $this->normalSessions[$sender->getName()] = 'actionDelAll';

                break;

            default:

                break;
        }

        return true;
    }

    public function onInteract(PlayerInteractEvent $event)
    {
        $block = $event->getBlock();
        $player = $event->getPlayer();

        if(isset($this->normalSessions[$player->getName()]))
        {
            if($this->normalSessions[$player->getName()] === 'action')
            {
                $player->sendMessage("Please write your command into chat (with a slash!), other players won't be able to see it!");
                $player->sendMessage("Execution Mode Tags: %pow, %op");
                $player->sendMessage("Special Tags: %p, %x, %y, %z, %l, %ip, %n");

                $this->normalSessions[$player->getName()] = $block;

                $this->cmdSessions[$player->getName()] = false;
            }
            else
            {
                if(($tempBlock = $this->getBlock($block, null, null, null)) instanceof Block)
                {
                    $this->deleteBlock($tempBlock);

                    $player->sendMessage("Removed all actions assigned to the block.");

                    unset($this->normalSessions[$player->getName()]);
                }
                else
                {
                    $player->sendMessage("Error: Block doesn't exist.");
                }
            }
        }

        if(!isset($this->normalSessions[$player->getName()]) && ($block = $this->getBlock($event->getBlock(), null, null, null)) instanceof Block && $event->getPlayer()->hasPermission("taptodo.tap"))
        {
            $block->executeCommands($event->getPlayer());
        }
    }

    public function onLevelLoad(LevelLoadEvent $event)
    {
        $this->getLogger()->info("Reloading blocks due to level " . $event->getLevel()->getName() . " loaded...");

        $this->parseBlockData();
    }

    public function onPlayerCommand(PlayerCommandPreprocessEvent $event)
    {
        $player = $event->getPlayer();

        if(isset($this->normalSessions[$player->getName()]))
        {
            $event->setCancelled();
        }

        if(isset($this->cmdSessions[$player->getName()]))
        {
            $block = $this->normalSessions[$player->getName()];
            $command = substr($event->getMessage(), 1);

            if(($tempBlock = $this->getBlock($block, null, null, null)) instanceof Block)
            {
                $tempBlock->addCommand($command);
            }
            else
            {
                $this->addBlock($block, $command);
            }

            $player->sendMessage("Added a new command to the block.");

            unset($this->cmdSessions[$player->getName()]);
            unset($this->normalSessions[$player->getName()]);

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
        $block = new Block(new Position($p->getX(), $p->getY(), $p->getZ(), $p->getLevel()), [$cmd], $this, count($this->config->get("blocks")));

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
