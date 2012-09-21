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

abstract class TagDefinition {
	private $element, $arg, $xargs;
	private $content = '';
	
	public final function create($element = null, $arg = null, $xargs = null) {
		$tag = clone $this;
		
		$tag->element = $element;
		$tag->arg     = $arg;
		$tag->xargs   = $xargs;
		
		return $tag;
	}
	
	protected final function __clone() {}
	
	public function AllowChild($tag) {
		return true;
	}
	
	public function Append($html) {
		$this->content .= $html;
	}
	
	public function Element() {
		return $this->element;
	}
	
	public function Bufferize($text) {
		$this->content .= htmlspecialchars($text);
	}
	
	public function Reduce() {
		return $this->content;
	}
}

class RootTag extends TagDefinition {
}

class MainTag extends TagDefinition {
	
}

class SimpleTag extends TagDefinition {
	public $before, $after;
	
	public function __construct($before, $after) {
		$this->before = $before;
		$this->after  = $after;
	}
	
	public function Reduce() {
		return $this->before.parent::Reduce().$this->after;
	}
}

