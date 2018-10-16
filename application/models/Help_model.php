<?php
/**
 * Sharif Judge online judge
 * @file Help_model.php
 * @author WYSIWYG <jiravatt.r@gmail.com>
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Help_model extends CI_Model
{

	public function __construct()
	{
		parent::__construct();
    }
    
    // ------------------------------------------------


    public function get_help_text($ext = 'html')
    {
        $path = APPPATH . "core/help.$ext";
		if (file_exists($path))
            $data = file_get_contents($path);
        else
            $data = '<p>File not found.</p>';
        
        return $data;
    }

    // ------------------------------------------------

    public function save_help_text($txt = '')
    {
        $path = APPPATH . "core";
        //show_error($path);
        // We parse markdown using Parsedown library
        $this->load->library('parsedown');
        // Save the markdown code
        file_put_contents("$path/help.md", $txt);
        // Convert markdown to html and save the html
        file_put_contents("$path/help.html", $this->parsedown->parse($txt));
        
    }

}
