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

//
// Some useful tools
//
abstract class TagTools {
	public static function SanitizeURL($url, $html_encoded = false) {
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
	protected $buffer_escape = true;
	protected $display = DISPLAY_INLINE;
	
	// Related objects
	protected $ctx, $parser;
	
	public function __construct() {}
	
	// === Tag creation utilities ==============================================
	
	public final function create(Context $ctx, $element = null, $arg = null, $xargs = null) {
		$tag = clone $this;
		
		$tag->element = $element ? $element : $this->element;
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
	
	//
	// Does this tag allow children ?
	//
	public function AllowChilds() {
		return true;
	}
	
	//
	// Append already escaped content to buffer
	//
	public function Append($html) {
		$this->content .= $html;
	}
	
	//
	// Should this tag be closed automatically ?
	//
	public function AutoClose() {
		return false;
	}
	
	//
	// Append unescaped data to buffer
	//
	public function Bufferize($text) {
		$this->content .= $this->buffer_escape ? htmlspecialchars($text) : $text;
	}
	
	//
	// Can $tag be shifted on this tag ?
	//
	public function CanShift($tag) {
		// DISPLAY_SPECIAL tags can't be shifted if this function is not overloaded
		if($tag->Display() == DISPLAY_SPECIAL)
			return false;
		
		// Automatically exit from an inline tag if we shift a block tag
		if($this->Display() == DISPLAY_INLINE && $tag->Display() == DISPLAY_BLOCK)
			$this->ctx->Reduce($this->Element());
		
		return true;
	}
	
	//
	// Return the display mode of this tag.
	// Should be one of the DISPLAY_* constants.
	//
	public function Display() {
		return $this->display;
	}
	
	//
	// Return the element of this tag (eg: b, i, url)
	//
	public function Element() {
		return $this->element;
	}
	
	//
	// Return the HTML code for this tag
	//
	public function Reduce() {
		return $this->content;
	}
}

// === Tag templates ===========================================================

//
// A very simple tag surrounding its content with user-defined strings.
//
class SimpleTag extends TagDefinition {
	protected $before, $after;
	
	public function __construct($before, $after, $block = false) {
		$this->before  = $before;
		$this->after   = $after;
		$this->display = $block ? DISPLAY_BLOCK : DISPLAY_INLINE;
	}
	
	public function Reduce() {
		$content = parent::Reduce();
		return empty($content) ? '': $this->before.$content.$this->after;
	}
}

//
// A leaf tag disallowing all children.
//
class LeafTag extends SimpleTag {
	public function __construct($before, $after, $block = false) {
		parent::__construct($before, $after, $block);
	}
	
	public function AllowChilds() {
		return false;
	}
}

class SingleTag extends TagDefinition {
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

// === Abstract tag templates ==================================================

//
// An abstract tag allowing an argument as content
//
abstract class ArgAsContentTag extends TagDefinition {
	public function __create() {
		if($this->arg)
			$this->Bufferize($this->arg);
	}
	
	public function AutoClose() {
		// Autoclose if argument is given (we already have the tag content)
		return $this->arg;
	}
}

// === System tags =============================================================

//
// The root tag
// Automatically creates the main tag when adding text or inline element to it
//
class RootTag extends SimpleTag {
	public function __construct() {
		parent::__construct('', '', true);
		$this->element = '$root';
	}
	
	public function Bufferize($text) {
		$main = $this->parser->MainTag()->create($this->ctx);
		$this->ctx->stack->Push($main);
		$main->Bufferize($text);
	}
	
	public function CanShift($tag) {
		if($tag->Display() == DISPLAY_INLINE):
			$main = $this->parser->MainTag()->create($this->ctx);
			$this->ctx->stack->Push($main);
		endif;
		
		return true;
	}
}

//
// The main tag, a simple <p> as a DISPLAY_INLINE element
//
class MainTag extends SimpleTag {
	public function __construct() {
		parent::__construct("<p>", "</p>");
		// Note: displayed inline so exits automatically when adding a block element.
	}
	
	public function __create() {
		$this->element = '$p';
	}
}

// === Main tags ===============================================================

//
// The [url] tag, supports embedded images
//
class LinkTag extends SimpleTag {
	protected $embedded_img = null;
	
	public function __construct() {
		parent::__construct('<a href="#">', '</a>');
	}
	
	public function __create() {
		if($this->arg)
			$this->arg = TagTools::SanitizeURL($this->arg);
	}
	
	public function CanShift($tag) {
		// We can't shift more than one image (with embedded mode)
		if($this->embedded_img)
			return false;
		
		// Hook ourself on the first [img] tag if arg is undefined
		if($tag instanceof ImageTag && !$this->arg) {
			$this->embedded_img = $tag;
			return true;
		}
		
		return $this->arg ? parent::CanShift($tag) : false;
	}
	
	public function Reduce() {
		if($this->embedded_img)
			$url = htmlspecialchars($this->embedded_img->GetURL());
		else
			$url = $this->arg ? htmlspecialchars($this->arg) : TagTools::SanitizeURL($this->content, true);
		
		if($url)
			$this->before = '<a href="'.$url.'">';
		
		return parent::Reduce();
	}
}

//
// The [img] tag
//
class ImageTag extends ArgAsContentTag {
	public function __construct() {
		parent::__construct();
		$this->buffer_escape = false;
	}
	
	public function Reduce() {
		// Image URL
		$url = $this->GetURL();
		
		// Alt text
		$alt = isset($this->xargs['alt']) ? $this->xargs['alt'] : basename($url);
		
		// Image styles
		$styles = array();
		
		if(isset($this->xargs['width']) && $size = TagTools::FormatSize($this->xargs['width']))
			$styles[] = 'width:'.$size;
		
		if(isset($this->xargs['height']) && $size = TagTools::FormatSize($this->xargs['height']))
			$styles[] = 'height:'.$size;
		
		$styles = !empty($styles) ? 'style="'.implode(';', $styles).'" ' : '';
		
		// Final tag
		return '<img src="'.htmlspecialchars($url).'" alt="'.htmlspecialchars($alt).'" '.$styles.'/>';
	}
	
	//
	// Return the unescaped URL for this image
	//
	public function GetURL() {
		return TagTools::SanitizeURL($this->content);
	}
}

//
// The [quote] tag, emulate a new document root
//
class QuoteTag extends RootTag {
	public function __construct() {
		parent::__construct();
		$this->before = '<blockquote>';
		$this->after = '</blockquote>';
	}
	
	public function Reduce() {
		if($author = $this->GetAuthorString()) {
			$this->content = '<div class="quote-author">'.htmlspecialchars($author).'</div>'.$this->content;
		}
		
		return parent::Reduce();
	}
	
	public function GetAuthorString() {
		return $this->arg ? $this->arg.' a écrit :' : false;
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
}
