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
// The tag stack used for parsing
//
class Stack {
	// The default size of new stacks
	public static $MAX_SIZE = 50;
	
	private $stack;          // The internal storage array
	private $stack_size;     // The size of this stack
	private $stack_head;     // Cached value of the stack head
	private $stack_head_ptr; // Pointer to the stack head
	
	private $mutated = false;
	
	public function __construct() {
		$this->stack = new \SplFixedArray($this->stack_size = self::$MAX_SIZE);
		$this->stack_head = null;
		$this->stack_head_ptr = -1;
	}
	
	//
	// Return the stack head
	//
	public function Head() {
		return $this->stack_head;
	}
	
	//
	// Push a new item onto the stack
	//
	public function Push(TagDefinition $item) {
		// Check for stack overflow
		if($this->stack_head_ptr + 1 >= $this->stack_size)
			return false;
		
		$this->mutated = true;
		
		// Insert the new head
		$this->stack[++$this->stack_head_ptr] = $this->stack_head = $item;
		return true;
	}
	
	//
	// Remove the top-most element of the stack and return it
	//
	public function Pop() {
		// Stack is empty
		if($this->stack_head_ptr < 0)
			return null;
		
		// Move the stack head
		if(--$this->stack_head_ptr >= 0)
			$this->stack_head = $this->stack[$this->stack_head_ptr];
		else
			$this->stack_head = null;
		
		$this->mutated = true;
		
		// Return the previous head
		return $this->stack[$this->stack_head_ptr+1];
	}
	
	//
	// Check if the stack contains a tag of the given element and return
	// this element's location (starting at 1) from the top of the stack.
	//
	public function Contains($el) {
		if($this->stack_head->Element() == $el) {
			// First element is the good one
			return 1;
		} else {
			// Check elements under the first one
			for($i = $this->stack_head_ptr - 1, $j = 2; $i > 0; $i--, $j++) {
				if($this->stack[$i]->Element() == $el) {
					return $j;
				}
			}
		}
		
		// Element is not in the stack
		return false;
	}
	
	//
	// Count how many elements are on the stack
	//
	public function Count() {
		return $this->stack_head_ptr + 1;
	}
	
	//
	// The stack doesn't clear previous cell when Pop() is called. Instead the
	// head pointer is simply redefined. This function ensure that every
	// stack-cells over the head pointer are correctly deallocated.
	//
	public function Purge() {
		for($i = $this->stack_head_ptr + 1; $i < $this->stack_size; $i++) {
			unset($this->stack[$i]);
		}
	}
	
	public function Mutated() {
		return $this->mutated;
	}
	
	Public function MutatedReset() {
		$this->mutated = false;
	}
}

