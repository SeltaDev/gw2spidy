<?php

namespace GW2Spidy\GW2API;

use GW2Spidy\Util\CurlRequest;
use GW2Spidy\Util\CacheHandler;

class API_Item {
    protected $item_id;
    protected $name;
    protected $description;
    protected $type;
    protected $sub_type;
    protected $level;
    protected $rarity;
    protected $vendor_value;
    protected $icon_file_id;
    protected $icon_file_signature;
    protected $game_types;
    protected $flags;
    protected $restrictions;
    protected $image;
    protected $infusion_slots;
    protected $infix_upgrade;
    protected $suffix_item_id;
    
    protected function __construct($API_Item) {        
        $this->item_id = (int) $API_Item['item_id'];
        $this->name = $API_Item['name'];
        $this->description = $API_Item['description'];
        $this->type = $API_Item['type'];
        $this->sub_type = null;
        $this->level = $API_Item['level'];
        $this->rarity = $API_Item['rarity'];
        $this->vendor_value = (int) $API_Item['vendor_value'];
        $this->icon_file_id = $API_Item['icon_file_id'];
        $this->icon_file_signature = $API_Item['icon_file_signature'];
        $this->game_types = $API_Item['game_types'];
        $this->flags = $API_Item['flags'];
        $this->restrictions = $API_Item['restrictions'];
        
        $this->image = getAppConfig('gw2spidy.gw2render_url')."/file/{$this->icon_file_signature}/{$this->icon_file_id}.png";
        
        $this->infusion_slots = null;
        $this->infix_upgrade = array();
        $this->suffix_item_id = null;
    }
    
    public function getTooltip() {
        $tooltip = <<<HTML
        <div class="p-tooltip-a p-tooltip_gw2 db-tooltip">
            <div class="p-tooltip-image db-image">
                <img src="{$this->getImageURL()}" alt="{$this->getHTMLName()}" />
            </div>
            {$this->getTooltipDescription()}
        </div>
HTML;
        return $tooltip;
    }
    
    public function getTooltipDescription() {
        $tooltip = <<<HTML
        
        <div class="p-tooltip-description db-description">
            <dl class="db-summary">
                <dt class="db-title gwitem-{$this->getRarityLower()}">{$this->getHTMLName()}</dt>
                <dd class="db-itemDescription">{$this->getHTMLDescription()}</dd>
                <dd class="db-itemDescription">{$this->getSoulboundStatus()}</dd>
            </dl>
        </div>
HTML;
        return $tooltip;
    }
    
    public static function getItem($itemID) {
        $cache = CacheHandler::getInstance('item_gw2api');
        $cacheKey = $itemID . "::" . substr(md5($itemID),0,10);
        $ttl      = 86400;
        
        if (!($API_JSON = $cache->get($cacheKey))) {
            try {
                $curl_item = CurlRequest::newInstance(getAppConfig('gw2spidy.gw2api_url')."/v1/item_details.json?item_id={$itemID}")
                    ->exec();
                $API_JSON = $curl_item->getResponseBody();
                
                $cache->set($cacheKey, $API_JSON, MEMCACHE_COMPRESSED, $ttl);
            } catch (Exception $e){
                $ttl = 600;
                $cache->set($cacheKey, null, MEMCACHE_COMPRESSED, $ttl);
                
                return null;
            }
        }
        
        $API_Item = json_decode($API_JSON, true);
        
        switch($API_Item['type']) {
            case "Armor": return new Armor($API_Item);
            case "Bag": return new Bag($API_Item);
            case "Consumable": return new Consumable($API_Item);
            case "Container": return new Container($API_Item);
            case "CraftingMaterial": return new CraftingMaterial($API_Item);
            case "Gathering": return new Gathering($API_Item);
            case "Gizmo": return new Gizmo($API_Item);
            case "MiniPet": return new MiniPet($API_Item);
            case "Tool": return new Tool($API_Item);
            case "Trinket": return new Trinket($API_Item);
            case "Trophy": return new Trophy($API_Item);
            case "UpgradeComponent": return new UpgradeComponent($API_Item);
            case "Weapon": return new Weapon($API_Item);
            default: return null;
        }
    }
    
    public function getType() {
        return $this->type;
    }
    
    public function getSubType() {
        return $this->sub_type;
    }
    
    public function getDescription() {
        return $this->description;
    }
    
    public function getHTMLDescription() {
        return htmlspecialchars(strip_tags($this->description));
    }
    
    public function getRarity() {
        return $this->rarity;
    }
    
    public function getHTMLName() {
        return htmlspecialchars($this->name);
    }
    
    public function getRarityLower() {
        return strtolower($this->rarity);
    }
    
    public function getLevel() {
        return $this->level;
    }
    
    public function getFormattedLevel() {
        return ($this->level > 0) ? "<dd class=\"db-requiredLevel\">Required Level: {$this->level}</dd>" : null;
    }
    
    public function getImageURL() {
        return $this->image;
    }
    
    public function getSoulboundStatus() {
        if (in_array("SoulBindOnUse", $this->flags)) {
            return "Soulbound On Use";
        }
        elseif (in_array("AccountBound", $this->flags)) {
            return "Account Bound";
        }
        
        return null;
    }
    
    public function cleanAttributes() {
        //Rename certain attributes to be in line with how they appear in game.
        if (isset($this->infix_upgrade['attributes'])) {
            array_walk($this->infix_upgrade['attributes'], function(&$attr){
                if ($attr['attribute'] == 'CritDamage')         $attr['attribute'] = 'Critical Damage';
                if ($attr['attribute'] == 'ConditionDamage')    $attr['attribute'] = 'Condition Damage';
                if ($attr['attribute'] == 'Healing')            $attr['attribute'] = 'Healing Power';
            });
        }
    }
    
    public function getAttributes() {
        return isset($this->infix_upgrade['attributes']) ? $this->infix_upgrade['attributes'] : array();
    }
    
    public function getFormattedAttributes() {
        $html = "";
        
        foreach ($this->getAttributes() as $attr) {
            $pct = ($attr['attribute'] == 'Critical Damage') ? '%' : null;
            $html .= "<dd class=\"db-stat\">+{$attr['modifier']}{$pct} {$attr['attribute']}</dd>\n";
        }
        
        return $html;
    }
    
    public function getSuffixItem() {
        $API_Item = ($this->suffix_item_id != "") ? API_Item::getItem($this->suffix_item_id) :  null;
        
        return $API_Item;
    }
    
    public function getFormattedSuffixItem() {
        $html = "";
        
        if (($Suffix_Item = $this->getSuffixItem()) !== null) {
            $buff = (method_exists($Suffix_Item, 'getBuffDescription')) ? $Suffix_Item->getBuffDescription() : null;
            $img = "<img alt='' src='{$Suffix_Item->getImageURL()}' height='16' width='16'>";
            
            $html .= "<dd class=\"db-slotted-item\">{$img} {$Suffix_Item->getHTMLName()}<br>{$buff}</dd>\n";
        }
        
        return $html;
    }
    
    public function getBuff() {
        return isset($this->infix_upgrade['buff']) ? $this->infix_upgrade['buff'] : null;
    }
    
    public function getBuffDescription() {
        if (isset($this->infix_upgrade['buff']['description'])) {
            return nl2br($this->infix_upgrade['buff']['description'], false);
        }
        
        return null;
    }
    
    protected function addBuffsToAttributes() {
        $buffs = explode("\n", $this->infix_upgrade['buff']['description']);
        
        $attributes_exist = (count($this->infix_upgrade['attributes']) > 0);
        
        foreach ($buffs as $buff) {
            list($modifier_stage1, $attribute) = explode(" ", $buff, 2);
            $modifier_stage2 = str_replace("+", "", $modifier_stage1);
            $modifier = (int) str_replace("%", "", $modifier_stage2);
            
            if (!$attributes_exist) {
                $this->infix_upgrade['attributes'][] = array('attribute' => $attribute, 'modifier' => $modifier);
            }
            else {
                foreach ($this->infix_upgrade['attributes'] as &$attr) {
                    $attr['modifier'] += ($attr['attribute'] == $attribute) ? $modifier : 0;
                }
            }
        }
    }
}