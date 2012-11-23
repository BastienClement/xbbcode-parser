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
	public function Shift(TagDefinition $tag) {
		// Clear the mutation tracking flag
		$this->stack->MutatedReset();
		
		if($this->stack->Head()->CanShift($tag)) {
			if($this->stack->Mutated()) {
				// Stack mutated by CanShift, try again!
				return $this->Shift($tag);
			} else {
				// Check nesting
				if($limit = $tag->MaxNesting()) {
					if(!$this->stack->CheckNesting($tag->Element(), $limit, $index)) {
						// This tag is over-nested
						$this->stack->Pick($index)->OverNestingIncr();
						return false;
					}
				}
				
				// Push the tag on the stack
				if($this->stack->Push($tag)) {
					if($tag->EmptyTag()) {
						// Auto-closing tag if this is an empty tag
						$this->Reduce($tag->Element());
					}
					
					return true;
				} else {
					// Something went wrong... Maybe a stack overflow
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
			$this->stack->Head()->Append($this->parser->HasFlag(PLAIN_TEXT) ? $tag->ReducePlaintext() : $tag->Reduce());
		}
	}
	
	//
	// Reduce elements on the stack until a given element is encountered.
	// If the stack doesn't contains this element, this function does nothing.
	//
	public function Reduce($el) {
		// Search for the given element into the stack
		if(!($idx = $this->stack->Find($el))) {
			// The stack doesn't contain the element
			return false;
		}
		
		// Attempt to consider over-nested closing tag when reducing
		if(($tag = $this->stack->Pick($idx)) && $tag->IsOverNested()) {
			$tag->OverNestingDecr();
			return false;
		}
		
		// How many elements from the top of the stack to this element (included) ?
		$nb = $this->stack->Count() - $idx;
		
		// Don't auto-close elements if the head does not allow children
		if(!$this->stack->Head()->AllowChilds() && $nb > 1) {
			return false;
		}
		
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
		$root = $this->stack->Pop();
		$html = $this->parser->HasFlag(PLAIN_TEXT) ? $root->ReducePlaintext() : $root->Reduce();
		
		// Clean the stack
		unset($this->stack);
		
		return $html;
	}
}

