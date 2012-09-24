<?php
//
//  Copyright (C) 2012 Unnamed Lab <http://www.unnamed.eu/>
//
//  Permission is hereby granted, free of charge, to any person obtaining
//  a copy of this software and associated documentation files (the
//  "Software"), to deal in the Software without restriction, including
//  without limitation the rights to use, copy, modify, merge, publish,
//  distribute, sublicense, and/or sell copies of the Software, and to
//  permit persons to whom the Software is furnished to do so, subject to
//  the following conditions:
//
//  The above copyright notice and this permission notice shall be included
//  in all copies or substantial portions of the Software.
//
//  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
//  EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
//  MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
//  IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
//  CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
//  TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
//  SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
//

namespace XBBC;

const DISPLAY_BLOCK   = 1;
const DISPLAY_INLINE  = 2;
const DISPLAY_SPECIAL = 3;

abstract class TagTools {
	public static function SanitizeURL($url) {
		if(!preg_match('#^([a-z0-9]{3,6})://#', $url))
			$url = 'http://'.$url;
		
		return str_replace(' ', '%20', $url);
	}
	
	public static function FormatSize($size, $units = 'px|%', $default = 'px') {
		if(preg_match('/^([0-9]*(?:\\.[0-9]+)?)\s*('.$units.')?$/', $size, $matches))
			return $matches[1].(isset($matches[2]) ? $matches[2] : $default);
		else
			return false;
	}
}

//
// The template for all tag definitions
//
abstract class TagDefinition {
	// Tag properties
	protected $element, $arg, $xargs;
	
	protected $content = '';
	protected $display = DISPLAY_INLINE;
	
	// Related objects
	protected $ctx, $parser;
	
	// === Tag creation utilities ==============================================
	
	public final function create(Context $ctx, $element = null, $arg = null, $xargs = null) {
		$tag = clone $this;
		
		$tag->element = $element;
		$tag->arg     = $arg;
		$tag->xargs   = $xargs;
		
		$tag->ctx     = $ctx;
		$tag->parser  = $ctx->parser;
		
		$tag->__create();
		
		return $tag;
	}
	
	protected final function __clone() {}
	protected function __create() {}
	
	// === Tag interface =======================================================
	
	public function AllowChilds() {
		return true;
	}
	
	public function Append($html) {
		$this->content .= $html;
	}
	
	public function AutoClose() {
		return false;
	}
	
	public function Bufferize($text) {
		$this->content .= htmlspecialchars($text);
	}
	
	public function CanShift($tag) {
		if($tag->Display() == DISPLAY_SPECIAL)
			return false;
		
		if($this->Display() == DISPLAY_INLINE && $tag->Display() == DISPLAY_BLOCK)
			$this->ctx->Reduce($this->Element());
		
		return true;
	}
	
	public function CanShiftOn() {
		return true;
	}
	
	public function Display() {
		return $this->display;
	}
	
	public function Element() {
		return $this->element;
	}
	
	public function OnShift() {
		// Noop
	}
	
	public function Reduce() {
		return $this->content;
	}
}

//
// The root tag
// Automatically creates the main tag when adding text or inline element to it
//
class RootTag extends TagDefinition {
	public function Bufferize($text) {
		$main = $this->parser->MainTag()->create($this->ctx);
		$this->ctx->stack->Push($main);
		$main->Bufferize($text);
	}
	
	public function CanShift($tag) {
		if($tag->Display() == DISPLAY_INLINE):
			$main = $this->parser->MainTag()->create($this->ctx);
			$this->ctx->Shift($main);
		endif;
		return true;
	}
}

//
// A very simple tag surrounding its content with user-defined strings.
//
class SimpleTag extends TagDefinition {
	protected $before, $after, $raw;
	
	public function __construct($before, $after, $block = false, $raw = false) {
		$this->before  = $before;
		$this->after   = $after;
		$this->display = $block ? DISPLAY_BLOCK : DISPLAY_INLINE;
		$this->raw     = $raw;
	}
	
	public function AllowChilds() {
		return !$this->raw;
	}
	
	public function Reduce() {
		$content = parent::Reduce();
		return empty($content) ? '': $this->before.$content.$this->after;
	}
}

class SingleTag extends SimpleTag {
	protected $html;
	
	public function __construct($html, $block = false) {
		$this->html    = $html;
		$this->display = $block ? DISPLAY_BLOCK : DISPLAY_INLINE;
	}
	
	public function AutoClose() {
		return true;
	}
	
	public function Reduce() {
		return $this->html;
	}
}

//
// The main tag, a simple <p> which exits automatically when adding a block
// element to it.
//
class MainTag extends SimpleTag {
	public function __construct() {
		parent::__construct("<p>", "</p>", true);
	}
	
	public function __create() {
		$this->element = '$p';
	}
	
	public function CanShift($tag) {
		if($tag->Display() == DISPLAY_BLOCK) {
			$this->ctx->Reduce('$p');
			return true;
		}
		
		return parent::CanShift($tag);
	}
}

class ListTag extends SimpleTag {
	public function __construct() {
		parent::__construct("<ul>", "</ul>", true);
	}
	
	public function CanShift($tag) {
		if($tag instanceof ListItemTag)
			return true;
		
		return parent::CanShift($tag);
	}
}

class ListItemTag extends SimpleTag {
	public function __construct() {
		parent::__construct("<li>", "</li>");
		$this->display = DISPLAY_SPECIAL;
	}
	
	public function CanShift($tag) {
		if($tag instanceof self) {
			$this->ctx->Reduce($this->element());
			return true;
		}
		
		return parent::CanShift($tag);
	}
	
	public function CanShiftOn($tag) {
		return $tag instanceof ListTag;
	}
}

class StandaloneTag extends TagDefinition {
	public function OnShift() {
		if(!empty($this->arg))
			$this->content = $this->arg;
	}
	
	public function AllowChilds() {
		return false;
	}
	
	public function AutoClose() {
		return !empty($this->arg);
	}
	
	public function Bufferize($txt) {
		$this->Append($txt);
	}
}

class LinkTag extends TagDefinition {
	protected $embedded_img = null;
	
	public function Bufferize($text) {
		if($this->embedded_img)
			return false;
		
		return $this->CanShift(null) ? parent::Bufferize($text) : $this->Append($text);
	}
	
	public function CanShift($tag) {
		if($this->embedded_img)
			return false;
		
		if($tag instanceof ImageTag && empty($this->arg)) {
			$this->embedded_img = $tag;
			return true;
		}
		
		return !empty($this->arg);
	}
	
	public function Reduce() {
		if($this->embedded_img)
			$url = $this->embedded_img->GetURL();
		else
			$url = TagTools::SanitizeURL(!empty($this->arg) ? $this->arg : $this->content);
		
		if($url) {
			$before = '<a href="'.htmlspecialchars($url).'">';
			$after  = '</a>';
		} else {
			$before = $after = '';
		}
		
		return $before.$this->content.$after;
	}
}

//
// The image tag
//
class ImageTag extends StandaloneTag {
	public function Reduce() {
		// Image URL
		$url = htmlspecialchars($this->GetURL());
		
		// Alternative text
		$alt = htmlspecialchars(isset($this->xargs['alt']) ? $this->xargs['alt'] : basename($url));
		
		// Image styles
		$styles = array();
		
		if(isset($this->xargs['width']) && $size = TagTools::FormatSize($this->xargs['width']))
			$styles[] = 'width:'.$size;
		
		if(isset($this->xargs['height']) && $size = TagTools::FormatSize($this->xargs['height']))
			$styles[] = 'height:'.$size;
		
		$styles = !empty($styles) ? 'style="'.implode(';', $styles).'" ' : '';
		
		// Final tag
		return '<img src="'.$url.'" alt="'.$alt.'" '.$styles.'/>';
	}
	
	public function GetURL() {
		return ($url = TagTools::SanitizeURL($this->content)) ? $url : $this->content;
	}
}

