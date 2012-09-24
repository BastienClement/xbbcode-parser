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

//
// A parsing context
//
class Context {
	// The parent parser
	public $parser;
	
	// The parse stack
	public $stack;
	
	public function __construct(Parser $parser) {
		$this->parser = $parser;
		
		$this->stack = new Stack($this);
		$this->stack->Push($parser->RootTag()->create($this));
	}
	
	// === Stack manipulation functions ========================================
	
	//
	// Add a tag on the stack
	//
	public function Shift($el, $arg = null, $xargs = null) {
		$tag = $el instanceof TagDefinition ? $el : $this->parser->TagDefinition($el)->create($this, $el, $arg, $xargs);
		
		$this->stack->MutatedReset();
		
		if($this->stack->Head()->CanShift($tag)) {
			if($this->stack->Mutated()) {
				return $this->Shift($tag);
			} else {
				if($this->stack->Push($tag)) {
					if($tag->AutoClose())
						$this->Reduce($tag->Element());
					return true;
				} else {
					return false;
				}
			}
		}
		
		return false;
	}
	
	//
	// Reduce a given number of element from the stack
	//
	public function ReduceElements($nb) {
		for($i = 0; $i < $nb; $i++) {
			$tag = $this->stack->Pop();
			$this->stack->Head()->Append($tag->Reduce());
		}
	}
	
	//
	// Reduce elements on the stack until a given element is encountered.
	// If the stack doesn't contains this element, this function does nothing.
	//
	public function Reduce($el) {
		// The stack doesn't contain the element
		if(!($nb = $this->stack->Contains($el)))
			return false;
		
		// Don't auto-close elements if the head does not allow children
		if(!$this->stack->Head()->AllowChilds() && $nb > 1)
			return false;
		
		$this->ReduceElements($nb);
		return true;
	}
	
	//
	// Close all tag left open on the stack and reduce the root tag
	//
	public function ReduceAll() {
		$open_tags = $this->stack->Count() - 1; // Ignore root tag for now
		if($open_tags < 0) {
			throw new Exception('Root tag is no longer open');
		}
		
		// Close all tags left open, don't touch the root tag
		$this->ReduceElements($open_tags);
		
		
		// The last tag in the stack is the root tag
		$html = $this->stack->Pop()->Reduce();
		
		// Clean the stack
		unset($this->stack);
		
		return $html;
	}
}

