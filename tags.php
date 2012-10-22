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
	
	public static function EscapeText($text, $strip = false) {
		return str_replace(
			array("\n\r", "\r\n", "\n", "\r"),
			'<br />',
			htmlspecialchars($strip ? trim($text) : $text)
		);
	}
}

//
// The template for all tag definitions
//
abstract class TagDefinition {
	// Tag properties
	protected $element, $arg, $xargs;
	
	protected $content = '';
	protected $text_buffer = '';
	protected $buffer_escape = true;
	protected $display = DISPLAY_INLINE;
	
	protected $max_nesting  = 1;
	protected $over_nesting = 0;
	
	protected $parse_smilies = true;
	
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
		
		if($tag->__create() === false)
			return false;
		
		return $tag;
	}
	
	protected final function __clone() {}
	protected function __create() {}
	
	// === Tag interface =======================================================
	
	//
	// Does this tag allow children ?
	//
	public function AllowChilds() { return true; }
	
	//
	// And text ?
	//
	public function AllowText() { return true; }
	
	//
	// And smilies ?
	//
	public function AllowSmilies() { return true; }
	
	//
	// Append already escaped content to buffer
	//
	public function Append($html) {
		$this->FlushText()->content .= $html;
		return $this;
	}
	
	//
	// Append unescaped data to buffer
	//
	public function Bufferize($text) {
		$this->text_buffer .= $text;
		return $this;
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
	public function Display() { return $this->display; }
	
	//
	// Return the tag's content after flushing the text buffer
	//
	protected function Content() {
		return $this->FlushText()->content;
	}
	
	//
	// Return the element of this tag (eg: b, i, url)
	//
	public function Element() { return $this->element; }
	
	//
	// Should this tag be closed automatically ?
	//
	public function EmptyTag() { return false; }
	
	//
	// Flush the text buffer into content buffer
	//
	protected function FlushText() {
		// Return if empty buffer
		if(!$this->text_buffer)
			return $this;
		
		// Escape HTML if required
		if($this->buffer_escape)
			$this->text_buffer = TagTools::EscapeText($this->text_buffer, $this->StripWhitespaces());
		
		// Parse smilies before reducing text-node
		if($this->AllowSmilies())
			$this->text_buffer = $this->ctx->parser->ParseSmilies($this->text_buffer, $this->buffer_escape);
		
		// Append the text node to the content buffer and clear the text buffer
		$this->content .= $this->text_buffer;
		$this->text_buffer = '';
		
		return $this;
	}
	
	//
	// How many times can this tag be nested into itself
	//
	public function MaxNesting() { return $this->max_nesting; }
	
	//
	// Increments or decrements the over-nesting counter
	//
	public final function OverNestingIncr() { $this->over_nesting++; }
	public final function OverNestingDecr() { $this->over_nesting--; }
	
	//
	// Check if this element is over-nested
	//
	public final function IsOverNested() { return $this->over_nesting > 0; }
	
	//
	// Also removes whitespaces when html-encoding
	//
	public function StripWhitespaces() { return $this->display != DISPLAY_INLINE; }
	
	//
	// Return the HTML code for this tag
	//
	public function Reduce() {
		return $this->Content();
	}
}

// === Tag templates ===========================================================

//
// A very simple tag surrounding its content with user-defined strings.
//
class SimpleTag extends TagDefinition {
	protected $before, $after;
	protected $strip_empty = true;
	
	public function __construct($before, $after, $block = false) {
		$this->before  = $before;
		$this->after   = $after;
		$this->display = $block ? DISPLAY_BLOCK : DISPLAY_INLINE;
	}
	
	public function Reduce() {
		$content = parent::Reduce();
		return empty($content) && $this->strip_empty ? '': $this->before.$content.$this->after;
	}
}

//
// A leaf tag disallowing all children.
//
class LeafTag extends SimpleTag {
	public function __construct($before, $after, $block = false) {
		parent::__construct($before, $after, $block);
	}
	
	public function AllowChilds()  { return false; }
	public function AllowSmilies() { return false; }
}

//
// A single tag without closing tag required and without content
//
class SingleTag extends TagDefinition {
	protected $html;
	
	public function __construct($html, $block = false) {
		$this->html    = $html;
		$this->display = $block ? DISPLAY_BLOCK : DISPLAY_INLINE;
	}
	
	public function EmptyTag() { return true; }
	public function Reduce() { return $this->html; }
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
	
	protected function __create() {
		$this->element = '$p';
	}
	
	public function Bufferize($txt) {
		$ps = preg_split('/[\n\r]{2,}/', $txt);
		
		if(($c = count($ps)) < 1) return;
		parent::Bufferize($ps[0]);
		
		for($i = 1; $i < $c; $i++) {
			$this->ctx->Reduce('$p');
			$this->ctx->stack->Head()->Bufferize($ps[$i]);
		}
	}
	
	public function Reduce() {
		$this->content = preg_replace('/^(\s|<br \/>)+|(\s|<br \/>)+$/', '', $this->Content());
		return parent::Reduce();
	}
}

