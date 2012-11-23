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

require "context.php";
require "stack.php";
require "tags.php";

//
// Parsing flags
//
const PARSE_META = 1;   // Only extract the meta-data array and return it
const PARSE_LEAD = 2;   // Only parse lead paragraph if available

const NO_CODE    = 4;   // Disable XBBCode parsing
const NO_SMILIES = 8;   // Disable Smilies parsing
const NO_HTMLESC = 16;  // Disable HTML escaping (only if NO_CODE is not set)

const SMILIES_OPTIMIZER = 32;  // Enable the optional smilies optimizer
const PLAIN_TEXT = 64;  // Produce plain-text output

//
// Regex used for parsing
//
const REGEX_TAG_NAME = '/[\w\-\*]/i';
const REGEX_XARGS = '/[ \t]+(\w+)(?:[=:](?|"((?:\\.|[^"])*)"|([^ \t]*)))?/';
const REGEX_TAG = <<<END
/^
	\\[
		(?|
			# Close tag
			(\\/)  ([\w\-\*]+)
		|
			# Open tag
			()    ([\\w\\-\\*]+)  (?:[=:](?|"((?:\\.|[^"])*)"|([^ \t]*)))?  ((?:  [ \\t]+\w+(?:[=:](?|"(?:\\\\.|[^"])*"|[^ \\t]*))?  )*)
		)
	\\]
$/x
END;

// =============================================================================

//
// The main XBBC Parser class.
//
class Parser {
	// Parser flags
	private $flags = 0;
	private $used = false;
	
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
	private $smilies_prefix = '';
	
	// Results from last parsing
	private $last_meta = null;
	private $last_has_lead = null;
	
	public function __construct($flags = 0) {
		$this->flags = $flags;
		
		$this->main_tag = new MainTag;
		$this->root_tag = new RootTag;
	}
	
	private function CheckUsed() {
		if($this->used) {
			throw new Exception("Once a parser is used, it cannot be modified");
		}
	}
	
	// === Flags functions ======================================================
	
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
		if($tag === null)
			return $this->halt_tag_name;
		
		$this->CheckUsed();
		$this->halt_tag_name = $this->ValidateTagName($tag, true);
		return $this;
	}
	
	//
	// Set and retrieve the special lead tag name
	//
	public function LeadTagName($tag = null) {
		if($tag === null)
			return $this->lead_tag_name;
		
		$this->CheckUsed();
		$this->lead_tag_name = $this->ValidateTagName($tag, true);
		return $this;
	}
	
	//
	// Set and retrieve the special meta tag name
	//
	public function MetaTagName($tag = null) {
		if($tag === null)
			return $this->meta_tag_name;
		
		$this->CheckUsed();
		$this->meta_tag_name = $this->ValidateTagName($tag, true);
		return $this;
	}
	
	//
	// Set the main text tag definition (typically a <p>)
	//
	public function MainTag(TagDefinition $mainTag = null) {
		if($mainTag === null)
			return $this->main_tag;
		
		$this->CheckUsed();
		$this->main_tag = $mainTag;
		return $this;
	}
	
	//
	// Set the root tag definition
	//
	public function RootTag(TagDefinition $rootTag = null) {
		if($rootTag === null)
			return $this->root_tag;
		
		$this->CheckUsed();
		$this->root_tag = $rootTag;
		return $this;
	}
	
	//
	// Define a new tag for this parser
	//
	public function DefineTag($tag_name, TagDefinition $tag_def) {
		$this->CheckUsed();
		
		$tag_name = $this->ValidateTagName($tag_name, true);
		$this->tags[$tag_name] = $tag_def;
		
		return $this;
	}
	
	//
	// Remove a tag from the parser tags-table
	//
	public function RemoveTag($tag_name) {
		$this->CheckUsed();
		unset($this->tags[$tag_name]);
		return $this;
	}
	
	//
	// Check if a given tag is defined, optionally including specials tags
	//
	public function TagDefined($tag, $include_specials = false) {
		// Check standards tags
		if(isset($this->tags[$tag]))
			return true;
		
		// Check special tags
		if($include_specials &&
			($tag == $this->halt_tag_name
			|| $tag == $this->lead_tag_name
			|| $tag == $this->meta_tag_name)) {
			return true;
		}
			
		return false;
	}
	
	//
	// Return the TagDefinition for a previously defined tag
	//
	public function TagDefinition($tag) {
		return isset($this->tags[$tag]) ? $this->tags[$tag] : null;
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
		if(!$this->IsValidTagName($tag))
			throw new Exception("Invalid tag name: [$tag]");
		
		// If required, check that the given tag name is not already defined
		if($check_defined && $this->TagDefined($tag, true))
			throw new Exception("Tag [$tag] is already defined");
		
		return strtolower($tag);
	}
	
	// === Smilies functions ===================================================
	
	//
	// Define a single smiley or replace an already existing one
	//
	public function DefineSmiley($code, $img) {
		$this->CheckUsed();
		$this->smilies[$code] = $img;
	}
	
	//
	// Remove a single smiley from the parser smilies table
	//
	public function RemoveSmiley($code) {
		$this->CheckUsed();
		unset($this->smilies[$code]);
	}
	
	//
	// Clean the smilies table, removing all defined smilies
	//
	public function RemoveAllSmilies() {
		$this->CheckUsed();
		$this->smilies = array();
	}
	
	//
	// Set and retrieve the smilies prefix
	//
	public function SmiliesPrefix($prefix = null) {
		if($prefix === null)
			return $this->smilies_prefix;
		
		$this->CheckUsed();
		$this->smilies_prefix = $prefix;
		return $this;
	}
	
	// === Main parsing functions ==============================================
	
	//
	// Main parsing function, take XBBCode as input and outputs HTML
	//
	public function Parse($code) {
		$this->used = true;
		
		// Line-breaks normalization
		$code = str_replace(array("\n\r", "\r\n", "\n", "\r"), "\n", $code);
		
		// Handle halt parser
		if($this->halt_tag_name && ($halt_offset = stripos($code, "[$this->halt_tag_name]")) !== false)
			$code = substr($code, 0, $halt_offset);
		
		// Extract meta data
		if($this->meta_tag_name) {
			$meta = array();
			
			$meta_callback = function($matches) use (&$meta) {
				$meta[strtolower($matches[1])] = $matches[2];
				return ""; // Delete the meta from the code
			};
			
			$meta_regex = "/\[$this->meta_tag_name\s*(\w+)\s*\](.*?)\[\/$this->meta_tag_name\]/is";
			$code = preg_replace_callback($meta_regex, $meta_callback, $code);
			
			if($this->HasFlag(PARSE_META))
				return $meta;
			else
				$this->last_meta = $meta;
		} else {
			$this->last_meta = null;
		}
		
		// Handle lead parsing
		if($this->lead_tag_name && ($lead_offset = stripos($code, "[$this->lead_tag_name]")) !== false) {
			$this->last_has_lead = true;
			if($this->HasFlag(PARSE_LEAD))
				$code = substr($code, 0, $lead_offset);
			else
				$code = substr($code, 0, $lead_offset).substr($code, $lead_offset + strlen($this->lead_tag_name) + 2);
		} else {
			$this->last_has_lead = false;
		}
		
		// Parse XBBCode if not disabled
		if(!$this->HasFlag(NO_CODE)) {
			// Lexer
			list($tokens, $t_count) = $this->SplitTokens($code);
			
			// Parser
			$ctx = new Context($this);
			
			foreach($tokens as $token) {
				// Reading a tag
				if($token[0] == '[' && preg_match(REGEX_TAG, $token, $matches)) {
					@list(, $closing, $el, $arg, $xargs) = $matches;
					
					// Check if this tag is defined
					if($this->TagDefined($el)) {
						if($closing) {
							// Reduced successfully
							if($ctx->Reduce($el)) {
								continue;
							}
						} else if($ctx->stack->Head()->AllowChilds()) {
							$arg   = (!empty($arg)) ? stripcslashes($arg) : null;
							$xargs = (!empty($xargs) && ($xargs[0] == ' ' || $xargs[0] == "\t"))
								? $this->ParseXArgs($xargs)
								: null;
							
							$tag = $this->TagDefinition($el)->create($ctx, $el, $arg, $xargs);
							
							// Shifted successfully
							if($tag && $ctx->Shift($tag)) {
								continue;
							}
						}
					}
				}
				
				// Not reading a tag
				if($ctx->stack->Head()->AllowText())
					$ctx->stack->Head()->Bufferize($token);
			}
			
			// Compile and generate HTML from the stack
			$html = $ctx->ReduceAll();
			
			if($this->HasFlag(PLAIN_TEXT) && !$this->HasFlag(NO_HTMLESC)) {
				$html = htmlspecialchars($html);
			}
		} else {
			$html = $this->HasFlag(NO_HTMLESC) ? $code : htmlspecialchars($code);
			
			// Parse smilies if not disabled
			if(!$this->HasFlag(NO_SMILIES))
				$html = $this->ParseSmilies($html);
		}
		
		return $html;
	}

	//
	// Metadata from last parsing
	//
	public function LastMeta() {
		return $this->last_meta;
	}
	
	//
	// Does the last string parsed used the [more] tag ?
	//
	public function LastHasLead() {
		return $this->last_has_lead;
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
		preg_match_all(REGEX_XARGS, $xargs, $matches, PREG_SET_ORDER);
		
		$xargs = array();
		foreach($matches as $match)
			$xargs[strtolower($match[1])] = isset($match[2]) ? stripcslashes($match[2]) : true;
		
		return $xargs;
	}

	//
	// Parse and replace smilies in the XBBC document
	//
	public function ParseSmilies($text, $escaped = false) {
		// Check if we need to handle smilies
		if($this->HasFlag(NO_SMILIES) || empty($this->smilies))
			return $text;
		
		// Flag the parser as used
		$this->used = true;
		
		// The replacer function
		$smilies_replacer = function($matches) use ($escaped) {
			// Decode the smiley if the input was htmlspecialchars'd
			$smiley = $escaped ? htmlspecialchars_decode($matches[0]) : $matches[0];
			
			if(isset($this->smilies[$smiley])) {
				$url = $this->smilies_prefix.$this->smilies[$smiley];
				return '<img src="'.$url.'" alt="'.htmlspecialchars($smiley).'" />';
			} else {
				return $matches[0];
			}
		};
		
		// Regex cache & compiler
		static $regex, $e_regex, $smilies_identifiers;
		if(!$regex) {
			$regex = $e_regex = '/(?<=\s|<br \/>)(?:';
			
			foreach($this->smilies as $smiley => $_) {
				$regex   .= preg_quote($smiley, '/').'|';
				$e_regex .= preg_quote(htmlspecialchars($smiley), '/').'|';
			}
			
			$regex   = substr($regex, 0, -1).')(?=\s|<br \/>)/im';
			$e_regex = substr($e_regex, 0, -1).')(?=\s|<br \/>)/im';
			
			// --- Smilies optimizer ---
			if($this->HasFlag(SMILIES_OPTIMIZER)) {
				$smilies_identifiers = array();
				$buckets = array();
				
				foreach($this->smilies as $smiley => $_) {
					// Split smiliey to chars and put them in buckets
					foreach(str_split($smiley) as $char) {
						if(!isset($buckets[$char]))
							$buckets[$char] = array();
						
						$buckets[$char][] = $smiley;
					}
				}
				
				// Move the biggest bucket first
				uasort($buckets, function($a, $b) {
					return count($b) - count($a);
				});
				
				// Keep trace of already encountered smiley
				$smilies_identified = array();
				
				// Clean up buckets a bit
				foreach($buckets as $char => &$bucket) {
					// Remove duplicated smilies already encountered
					$bucket = array_filter($bucket, function($smiley) use (&$smilies_identified) {
						if(isset($smilies_identified[$smiley])) {
							return false;
						} else {
							$smilies_identified[$smiley] = true;
							return true;
						}
					});
					
					// Bucket is not empty, so its char-key is useful for smilies detection
					if(count($bucket) > 0) {
						$smilies_identifiers[] = $char;
					}
				}
			}
		}
		
		// Smilies replacing
		
		if($this->HasFlag(SMILIES_OPTIMIZER)) {
			$found = false;
			foreach($smilies_identifiers as $char) {
				// Assuming most text nodes doesn't contains smilies, strpos
				// fails faster than a useless preg_replace.
				if(strpos($text, $char) !== false) {
					$found = true;
					break;
				}
			}
		} else {
			$found = true;
		}
		
		if($found) {
			$text = preg_replace_callback($escaped ? $e_regex : $regex, $smilies_replacer, " $text ");
			return substr($text, 1, -1);
		} else {
			return $text;
		}
	}
}
