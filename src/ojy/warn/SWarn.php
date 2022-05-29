<?php

namespace ojy\warn;

use BandAPI\BandAPI;
use CortexPE\DiscordWebhookAPI\Embed;
use CortexPE\DiscordWebhookAPI\Message;
use CortexPE\DiscordWebhookAPI\Webhook;
use DateTime;
use DateTimeZone;
use OnixUtils\OnixUtils;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\permission\DefaultPermissions;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;

class SWarn extends PluginBase implements Listener{

	/** @var string */
	public const PREFIX = "§d<§f시스템§d> §f";

	/** @var Config */
	public static Config $data;
	/** @var array */
	public static array $db;

	/** @var Config */
	public static Config $setting;

	/** @var Webhook */
	public static Webhook $webhook;

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		self::$webhook = new Webhook("");

		self::$setting = new Config($this->getDataFolder() . "Setting.yml", Config::YAML, [
			"ban-warn-count" => 5,
			"ip-ban-warn-count" => 5
		]);
		self::$data = new Config($this->getDataFolder() . "WarnData.yml", Config::YAML, [
			"warn" => [],
			"players" => [],
			"ip-bans" => []
		]);
		self::$db = self::$data->getAll();
		OnixUtils::command("경고", "경고 명령어 입니다.", [], false, function(CommandSender $sender, string $commandLabel, array $args) : void{
			if(!isset($args[0]))
				$args[0] = 'x';
			switch($args[0]){
				case "정보":
				case "확인":
					if(!isset($args[1]))
						$args[1] = $sender->getName();
					if(Server::getInstance()->getPlayerByPrefix($args[1]) !== null)
						$args[1] = Server::getInstance()->getPlayerByPrefix($args[1])->getName();
					$args[1] = strtolower($args[1]);
					if(isset(self::$db["warn"][$args[1]]) && count(self::$db["warn"][$args[1]]) > 0){
						$sender->sendMessage(self::PREFIX . "경고 목록을 출력합니다.");
						foreach(self::$db["warn"][$args[1]] as $warnId => $data){
							$amount = $data[0];
							$why = $data[1];
							$who = $data[2] ?? "관리자";
							$when = $data[3] ?? "알 수 없음";
							$sender->sendMessage("§l§b[{$warnId}] §r§7경고 수: {$amount}, 사유: {$why}, 처리자: {$who}, 처리일시: {$when}");
						}
					}else{
						$sender->sendMessage(self::PREFIX . "받은 경고가 없습니다.");
					}
					break;
				case "제거":
					if($sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
						if(isset($args[1]) && isset($args[2])){
							$v = array_shift($args);
							$name = array_shift($args);
							$warnId = array_shift($args);
							if(Server::getInstance()->getPlayerByPrefix($name) !== null)
								$name = Server::getInstance()->getPlayerByPrefix($name)->getName();
							if(isset(self::$db["warn"][strtolower($name)][$warnId])){
								$sender->sendMessage(self::PREFIX . "{$name}님의 {$warnId}번 경고를 {$v}했습니다.");
								unset(self::$db["warn"][strtolower($name)][$warnId]);
							}else{
								$sender->sendMessage(self::PREFIX . "경고 정보를 찾을 수 없습니다.");
							}
						}else{
							$sender->sendMessage(self::PREFIX . "/경고 제거 [닉네임] [번호]");
						}
					}else{
						$sender->sendMessage(self::PREFIX . "이 명령어를 사용할 권한이 없습니다.");
					}
					break;
				case "추가":
					if($sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
						$v = array_shift($args);
						$name = array_shift($args);
						$amount = array_shift($args);
						if(isset($name) && isset($amount) && is_numeric($amount)){
							if(Server::getInstance()->getPlayerByPrefix($name) !== null)
								$name = Server::getInstance()->getPlayerByPrefix($name)->getName();
							if(count($args) <= 0)
								$why = "서버 관리자의 경고";else
								$why = implode(" ", $args);
							self::addWarn($name, $amount, $why, $sender->getName());
						}else{
							$sender->sendMessage(self::PREFIX . "/경고 추가 [닉네임] [횟수] [사유]");
						}
					}else{
						$sender->sendMessage(self::PREFIX . "이 명령어를 사용할 권한이 없습니다.");
					}
					break;
				default:
					if($sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
						$sender->sendMessage(self::PREFIX . "/경고 추가 [닉네임] [횟수] [사유]");
						$sender->sendMessage(self::PREFIX . "/경고 제거 [닉네임] [번호]");
					}
					$sender->sendMessage(self::PREFIX . "/경고 확인 [닉네임] | 플레이어의 경고 정보를 확인합니다.");
					break;
			}
		});
	}

	protected function onDisable() : void{
		self::$data->setAll(self::$db);
		self::$data->save();
	}

	public static function setBanWarnCount(int $count){
		self::$setting->set("ban-warn-count", $count);
		self::$setting->save();
	}

	public static function setIpBanWarnCount(int $count){
		self::$setting->set("ip-ban-warn-count", $count);
		self::$setting->save();
	}

	public static function getBanWarnCount(){
		return self::$setting->get("ban-warn-count");
	}

	public static function getIpBanWarnCount(){
		return self::$setting->get("ip-ban-warn-count");
	}

	public static function getTotalWarn(string $playerName){
		$playerName = strtolower($playerName);
		if(isset(self::$db["warn"][$playerName])){
			$res = 0;
			foreach(self::$db["warn"][$playerName] as $warnId => $data){
				$res += $data[0];
			}
			return $res;
		}
		return 0;
	}

	public static function addWarn(string $playerName, int $amount, string $why, string $who = "관리자") : bool{
		$res = false;
		$playerName = strtolower($playerName);
		if(!isset(self::$db["warn"][$playerName]))
			self::$db["warn"][$playerName] = [];
		self::$db["warn"][$playerName][] = [$amount, $why, $who, date("Y년 m월 d일 h시 i분 s초")];
		$total = self::getTotalWarn($playerName);
		Server::getInstance()->broadcastMessage(self::PREFIX . "{$playerName}님에게 §a\"{$why}§r§a\"§f의 사유로 경고 {$amount}이(가) 부여되었습니다.");
		Server::getInstance()->broadcastMessage(self::PREFIX . "{$playerName}님의 누적 경고 수는 {$total} 입니다.");
		if($total >= self::getBanWarnCount()){
			Server::getInstance()->broadcastMessage(self::PREFIX . "{$playerName} 님이 경고수 초과로 이용이 제한되었습니다.");
			if(Server::getInstance()->getPlayerExact($playerName) !== null)
				Server::getInstance()->getPlayerExact($playerName)->kick(self::PREFIX . "경고수 초과로 이용이 제한되었습니다.\n총 경고수: " . self::getTotalWarn($playerName), false);
			$res = true;
		}
		if($total >= self::getIpBanWarnCount()){
			$ips = [];
			if(isset(self::$db["players"][$playerName]))
				$ips = self::$db["players"][$playerName]["ip"];
			$bans = [];
			foreach($ips as $ip){
				if(!in_array($ip, self::$db["ip-bans"])){
					self::$db["ip-bans"][] = $ip;
					$bans += self::ipCheck($ip);
				}
			}
			$bans = array_unique($bans);
			foreach($bans as $playerName){
				Server::getInstance()->broadcastMessage(self::PREFIX . "{$playerName}님이 경고수 초과로 아이피밴 되었습니다.");
				if(Server::getInstance()->getPlayerExact($playerName) !== null)
					Server::getInstance()->getPlayerExact($playerName)->kick(self::PREFIX . "경고수 초과로 이용이 제한되었습니다.\n총 경고수: " . self::getTotalWarn($playerName), false);
			}
			$res = true;
		}
		if(Server::getInstance()->getPluginManager()->getPlugin("BandAPI") !== null){
			if($res){
				BandAPI::sendPost("[경고 처리 안내] #경고\n\n{$playerName}님에게 경고 {$amount}가 부여되어 이용이 제한되었습니다.\n총 경고수: {$total}\n사유: {$why}\n\n처리자: {$who}\n일시: " . date("Y년 m월 d일 h시 i분"));
				$embed = new Embed();
				$embed->setAuthor("오닉스서버 경고처리")->setDescription("오닉스서버 이용 제한 안내")->setColor(0xFF0000)->setTimestamp((new DateTime('now', new DateTimeZone("Asia/Seoul"))))->addField("대상자", $playerName)->addField("경고", (string) $amount)->addField("사유", $why)->addField("총 경고", (string) $total);
				$message = new Message();
				$message->addEmbed($embed);
				self::$webhook->send($message);
			}else{
				$embed = new Embed();
				$embed->setAuthor("오닉스서버 경고처리")->setDescription("오닉스서버 경고처리 안내")->setColor(0xFF0000)->setTimestamp((new DateTime('now', new DateTimeZone("Asia/Seoul"))))->addField("대상자", $playerName)->addField("경고", (string) $amount)->addField("사유", $why)->addField("총 경고", (string) $total);
				$message = new Message();
				$message->addEmbed($embed);
				self::$webhook->send($message);
				BandAPI::sendPost("[경고 처리 안내] #경고처리\n\n{$playerName}님에게 경고 {$amount}가 부여되었습니다.\n총 경고수: {$total}\n사유: {$why}\n\n처리자: {$who}\n일시: " . date("Y년 m월 d일 h시 i분"));
			}
		}
		return $res;
	}

	public static function ipCheck(string $ip) : array{
		$res = [];
		foreach(self::$db["players"] as $playerName => $data){
			if($data["ip"] === $ip)
				$res[] = $playerName;
		}
		return $res;
	}

	public function onLogin(PlayerPreLoginEvent $event){
		$player = $event->getPlayerInfo();
		if(!isset(self::$db["players"][strtolower($player->getUsername())])){
			self::$db["players"][strtolower($player->getUsername())] = [
				"name" => $player->getUsername(),
				"ip" => [$event->getIp()]
			];
		}else{
			if(!in_array($event->getIp(), self::$db["players"][strtolower($player->getUsername())]["ip"])){
				self::$db["players"][strtolower($player->getUsername())]["ip"][] = $event->getIp();
			}
		}
		////// BAN CHECK //////
		if(self::getBanWarnCount() <= ($c = self::getTotalWarn($player->getUsername()))){
			$event->setKickReason(PlayerPreLoginEvent::KICK_REASON_BANNED, self::PREFIX . "경고수 초과로 이용이 제한되었습니다.\n총 경고수: {$c}");
			return;
		}
		///// IP BAN CHECK /////
		if(self::getIpBanWarnCount() <= $c){
			if(in_array($event->getIp(), self::$db["ip-bans"])){
				$event->setKickReason(PlayerPreLoginEvent::KICK_REASON_BANNED, self::PREFIX . "경고수 초과로 이용이 제한되었습니다.\n총 경고수: {$c}");
				return;
			}
		}
	}
}