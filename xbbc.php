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

class Exception extends \Exception {};

require "stack.php";
require "stdlib.php";
require "tags.php";

//
// Parsing flags
//
const PARSE_META = 1;   // Only extract the meta-data array and return it
const PARSE_LEAD = 2;   // Only parse lead paragraph if available
const NO_CODE    = 4;   // Disable XBBCode parsing
const NO_SMILIES = 8;   // Disable Smilies parsing
const NO_HTMLESC = 16;  // Disable HTML escaping

//
// Regex used for parsing
//
const REGEX_TAG_NAME = '/[\w\-\*]/i';
const REGEX_TAG = <<<END
/^
	\[
		(?|
			# Close tag
			(\/)  ([\w\-\*]+)
		|
			# Open tag
			()    ([\w\-\*]+)  (?:[=:]([^ \t]+))?  ((?:[ \t]+\w+(?:[=:][^ \t]*)?)*)
		)
	\]
$/x
END;

// =============================================================================

//
// The main XBBC Parser class.
//
class Parser {
	// Parser flags
	private $flags = 0;
	
	// Tag names for special tags
	private $halt_tag_name = "halt";
	private $lead_tag_name = "more";
	private $meta_tag_name = "meta";
	
	// Tag definition for main tags
	private $main_tag;
	private $root_tag;
	
	// Tags and smilies list
	private $tags    = array();
	private $smilies = array();
	
	// The parse stack
	private $stack;
	
	public function __construct($flags = 0) {
		$this->flags = $flags;
		
		$this->main_tag = new MainTag;
		$this->root_tag = new RootTag;
	}
	
	// === Stdlib function =====================================================
	
	//
	// Import default tags from the stdlib
	//
	public function ImportStdTags() {
		StdTags::import($this);
		return $this;
	}
	
	//
	// Import default smilies from the stdlib
	//
	public function ImportStdSmilies() {
		StdSmilies::import($this);
		return $this;
	}
	
	// === Flags function ======================================================
	
	//
	// Enable a specific flag
	//
	public function SetFlag($flag) {
		$this->flags = $this->flags | $flag;
		return $this;
	}
	
	//
	// Disable a specific flag
	//
	public function UnsetFlag($flag) {
		$this->flags = $this->flags & ~$flag;
		return $this;
	}
	
	//
	// Check if a given flag is enabled
	//
	public function HasFlag($flag) {
		return (bool) ($this->flags & $flag);
	}
	
	//
	// Set and retrieves all parses flags at once
	//
	public function Flags($flags = null) {
		if($flags === null):
			return $this->flags;
		endif;
		
		$this->flags = (int) $flags;
		return $this;
	}
	
	// === Tag functions =======================================================
	
	//
	// Set and retrieve the special halt tag name
	//
	public function HaltTagName($tag = null) {
		if($tag === null):
			return $this->halt_tag_name;
		endif;
		
		$this->halt_tag_name = $this->ValidateTagName($tag, true);
		return $this;
	}
	
	//
	// Set and retrieve the special lead tag name
	//
	public function LeadTagName($tag = null) {
		if($tag === null):
			return $this->lead_tag_name;
		endif;
		
		$this->lead_tag_name = $this->ValidateTagName($tag, true);
		return $this;
	}
	
	//
	// Set and retrieve the special meta tag name
	//
	public function MetaTagName($tag = null) {
		if($tag === null):
			return $this->meta_tag_name;
		endif;
		
		$this->meta_tag_name = $this->ValidateTagName($tag, true);
		return $this;
	}
	
	//
	// Set the main text tag definition (typically a <p>)
	//
	public function MainTag(TagDefinition $mainTag = null) {
		if($mainTag === null):
			return $this->main_tag;
		endif;
		
		$this->main_tag = $mainTag;
		return $this;
	}
	
	//
	// Set the root tag definition
	//
	public function RootTag(TagDefinition $rootTag = null) {
		if($rootTag === null):
			return $this->root_tag;
		endif;
		
		$this->root_tag = $rootTag;
		return $this;
	}
	
	//
	// Define a new tag for this parser
	//
	public function DefineTag($tag_name, TagDefinition $tag_def) {
		$tag_name = $this->ValidateTagName($tag_name, true);
		$this->tags[$tag_name] = $tag_def;
		
		return $this;
	}
	
	//
	// Check if a given tag is defined, optionally including specials tags
	//
	public function TagDefined($tag, $include_specials = false) {
		// Check standards tags
		if(isset($this->tags[$tag])):
			return true;
		endif;
		
		// Check special tags
		if($include_specials &&
			($tag == $this->halt_tag_name
			|| $tag == $this->lead_tag_name
			|| $tag == $this->meta_tag_name)):
			return true;
		endif;
		
		return false;
	}
	
	//
	// Check if a tag name matches the TAG_NAME regex
	//
	public function IsValidTagName($tag) {
		return (bool) preg_match(REGEX_TAG_NAME, $tag);
	}
	
	//
	// Check and sanitize a tag name
	//
	private function ValidateTagName($tag, $check_defined = false) {
		if(!$this->IsValidTagName($tag)):
			throw new Exception("Invalid tag name: [$tag]");
		endif;
		
		// If required, check that the given tag name is not already defined
		if($check_defined && $this->TagDefined($tag, true)):
			throw new Exception("Tag [$tag] is already defined");
		endif;
		
		return strtolower($tag);
	}
	
	// === Stack manipulation functions ========================================
	
	//
	// Add a tag on the stack
	//
	private function Shift($el, $arg, $xargs) {
		$tag = $this->tags[$el]->create($el, $arg, $xargs);
		return $this->stack->Push($tag);
	}
	
	//
	// Reduce a given number of element from the stack
	//
	private function ReduceElements($nb) {
		for($i = 0; $i < $nb; $i++):
			$tag = $this->stack->Pop();
			$this->stack->Head()->Append($tag->Reduce());
		endfor;
	}
	
	//
	// Reduce elements on the stack until a given element is encountered.
	// If the stack doesn't contains this element, this function does nothing.
	//
	private function Reduce($el) {
		if(!($nb = $this->stack->Contains($el))):
			return false;
		endif;
		
		$this->ReduceElements($nb);
		return true;
	}
	
	//
	// Close all tag left open on the stack and reduce the root tag
	//
	private function ReduceAll() {
		$open_tags = $this->stack->Count() - 1; // Ignore root tag for now
		if($open_tags < 0):
			throw new Exception('Root tag is no longer open');
		endif;
		
		// Close all tags left open, don't touch the root tag
		$this->ReduceElements($open_tags);
		
		
		// The last tag in the stack is the root tag
		$html = $this->stack->Pop()->Reduce();
		
		// Clean the stack
		unset($this->stack);
		
		return $html;
	}
	
	// === Main parsing functions ==============================================
	
	//
	// Main parsing function, take XBBCode as input and outputs HTML
	//
	public function Parse($code) {
		// Handle halt parser
		if($this->halt_tag_name && ($halt_offset = stripos($code, "[$this->halt_tag_name]")) !== false):
			$code = substr($code, 0, $halt_offset);
		endif;
		
		// Extract meta data
		if($this->meta_tag_name):
			$meta = array();
			
			$meta_callback = function($matches) use (&$meta) {
				$meta[strtolower($matches[1])] = $matches[2];
				return ""; // Delete the meta from the code
			};
			
			$meta_regex = "/\[$this->meta_tag_name\s*(\w+)\s*\](.*?)\[\/$this->meta_tag_name\]/is";
			$code = preg_replace_callback($meta_regex, $meta_callback, $code);
			
			if($this->HasFlag(PARSE_META)):
				return $meta;
			endif;
		endif;
		
		// Handle lead parsing
		if($this->lead_tag_name && ($lead_offset = stripos($code, "[$this->lead_tag_name]")) !== false):
			if($this->HasFlag(PARSE_LEAD)):
				$code = substr($code, 0, $lead_offset);
			else:
				$code = substr($code, 0, $lead_offset).substr($code, $lead_offset + strlen($this->lead_tag_name) + 2);
			endif;
		endif;
		
		// Parse XBBCode if not disabled
		if(!$this->HasFlag(NO_CODE)):
			// Lexer
			list($tokens, $t_count) = $this->SplitTokens($code);
			
			// Parser
			$this->stack = new Stack($this);
			$this->stack->Push($this->RootTag()->create());
			
			foreach($tokens as $token):
				// Get the top-most element from the tag stack
				$stack_head = $this->stack->Head();
				
				// Reading a tag
				if($token[0] == '[' && preg_match(REGEX_TAG, $token, $matches)):
					@list(, $closing, $el, $arg, $xargs) = $matches;
					
					// Check if this tag is defined
					if($this->TagDefined($el)):
						if($closing):
							if($this->Reduce($el)):
								// Reduced successfully
								continue;
							endif;
						elseif($stack_head->AllowChild($el)):
							$arg   = (!empty($arg)) ? $arg : null;
							$xargs = (!empty($xargs) && ($xargs[0] == ' ' || $xargs[0] == "\t"))
								? $this->ParseXArgs($xargs)
								: null;
							
							if($this->Shift($el, $arg, $xargs)):
								// Shifted successfully
								continue;
							endif;
						endif;
					endif;
				endif;
				
				// Not reading a tag
				$stack_head->Bufferize($token);
			endforeach;
			
			// Compile and generate HTML from the stack
			$html = $this->ReduceAll();
		else:
			$html = $this->HasFlag(NO_HTMLESC) ? $code : htmlspecialchars($code);
		endif;
		
		// Parse smilies if not disabled
		if(!$this->HasFlag(NO_SMILIES)):
			$html = $this->ParseSmilies($html);
		endif;
		
		return $html;
	}
	
	//
	// Split the XBBC document into tokens
	//
	private function SplitTokens($code) {
		$tokens = preg_split('/(\[.+?\])/', trim($code), null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		return array($tokens, count($tokens));
	}
	
	//
	// Parse the extended-arguments string
	//
	private function ParseXArgs($xargs) {
		preg_match_all('/[ \t]+(\w+)(?:[=:]([^ \t]*))?/', $xargs, $matches, PREG_SET_ORDER);
		
		$xargs = array();
		foreach($matches as $match):
			$xargs[$match[1]] = isset($match[2]) ? $match[2] : true;
		endforeach;
		
		return $xargs;
	}

	//
	// Parse and replace smilies in the XBBC document
	//
	private function ParseSmilies($html) {
		// TODO
		return $html;
	}
}

