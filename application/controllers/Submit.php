<?php
/**
 * Sharif Judge online judge
 * @file Submit.php
 * @author Mohammad Javad Naderi <mjnaderi@gmail.com>
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Submit extends CI_Controller
{

	private $data; //data sent to view
	private $assignment_root;
	private $problems;
	private $problem;//submitted problem id
	private $filetype; //type of submitted file
	private $ext; //uploaded file extension
	private $file_name; //uploaded file name without extension
	private $coefficient;


	// ------------------------------------------------------------------------


	public function __construct()
	{
		parent::__construct();
		if ( ! $this->session->userdata('logged_in')) // if not logged in
			redirect('login');
		$this->load->library('upload')->model('queue_model');
		$this->assignment_root = $this->settings_model->get_setting('assignments_root');

		// LEVEL mode
		if ($this->user->selected_assignment['level_mode'] == 1 && $this->user->level == 0)
		{
			$level = $this->assignment_model->get_current_level($this->user->selected_assignment['id'], $this->user->username);
			$this->problems = $this->assignment_model->all_problems($this->user->selected_assignment['id'], $level);
		}
		else
		    $this->problems = $this->assignment_model->all_problems($this->user->selected_assignment['id'], 0, true);

		    
		if ($this->user->selected_assignment['forever'] == 0)
		{
			$extra_time = $this->user->selected_assignment['extra_time'];
			$delay = shj_now()-strtotime($this->user->selected_assignment['finish_time']);
			ob_start();
			if ( eval($this->user->selected_assignment['late_rule']) === FALSE )
				$coefficient = "error";
			if (!isset($coefficient))
				$coefficient = "error";
			ob_end_clean();
		}
		else
		{
			$extra_time = 0;
			$delay = 0;
			$coefficient = 100;
		}

		$this->coefficient = $coefficient;
		

	}


	// ------------------------------------------------------------------------


	public function _language_to_type($language)
	{
		$language = strtolower ($language);
		switch ($language) {
			case 'c': return 'c';
			case 'c++': return 'cpp';
			case 'python 2': return 'py2';
			case 'python 3': return 'py3';
			case 'java': return 'java';
			case 'zip': return 'zip';
			case 'pdf': return 'pdf';
			case 'flowgorithm': return 'fprg'; // Flowgorithm file
			default: return FALSE;
		}
	}


	// ------------------------------------------------------------------------


	public function _language_to_ext($language)
	{
		$language = strtolower ($language);
		switch ($language) {
			case 'c': return 'c';
			case 'c++': return 'cpp';
			case 'python 2': return 'py';
			case 'python 3': return 'py';
			case 'java': return 'java';
			case 'zip': return 'zip';
			case 'pdf': return 'pdf';
			case 'flowgorithm': return 'fprg'; // Flowgorithm file
			default: return FALSE;
		}
	}
	
	
	// ------------------------------------------------------------------------


	public function _match($type, $extension)
	{
		switch ($type) {
			case 'c': return ($extension==='c'?TRUE:FALSE);
			case 'cpp': return ($extension==='cpp'?TRUE:FALSE);
			case 'py2': return ($extension==='py'?TRUE:FALSE);
			case 'py3': return ($extension==='py'?TRUE:FALSE);
			case 'java': return ($extension==='java'?TRUE:FALSE);
			case 'zip': return ($extension==='zip'?TRUE:FALSE);
			case 'pdf': return ($extension==='pdf'?TRUE:FALSE);
			case 'fprg': return ($extension==='fprg'?TRUE:FLASE); // Flowgorithm file
		}
	}


	// ------------------------------------------------------------------------


	public function _check_language($str)
	{
		if ($str=='0')
			return FALSE;
		if (in_array( strtolower($str),array('c', 'c++', 'python 2', 'python 3', 'java', 'zip', 'pdf', 'flowgorithm')))
			return TRUE;
		return FALSE;
	}


	// ------------------------------------------------------------------------


	public function index($mode = 'file')
	{
		$this->form_validation->set_rules('problem', 'problem', 'required|integer|greater_than[0]', array('greater_than' => 'Select a %s.'));
		$this->form_validation->set_rules('language', 'language', 'required|callback__check_language', array('_check_language' => 'Select a valid %s.'));

		if ($this->form_validation->run())
		{
		    if ($mode == 'file')
		    {
			if ($this->_upload())
				redirect('submissions/all');
			else
				show_error('Error Uploading File: '.$this->upload->display_errors());
		}
            else if ($mode == 'editor')
            {
                if($this->_upload('editor'))
                    redirect('submissions/all');
                else
                    show_error('Error Creating File. Please try again.');
            }
		}

		$this->data = array(
			'all_assignments' => $this->assignment_model->all_assignments(),
			'problems' => $this->problems,
			'in_queue' => FALSE,
			'coefficient' => $this->coefficient,
			'upload_state' => '',
			'problems_js' => '',
			'error' => '',
		);

		foreach ($this->problems as $problem)
		{
			$languages = explode(',', $problem['allowed_languages']);
			$items='';
			foreach ($languages as $language)
			{
				$items = $items."'".trim($language)."',";
			}
			$items = substr($items,0,strlen($items)-1);
			$this->data['problems_js'] .= "shj.p[{$problem['id']}]=[{$items}]; ";
		}
		if ($this->user->selected_assignment['id'] == 0)
			$this->data['error']='Please select an assignment first.';
	    else if ($this->user->level < 2 &&  ! $this->assignment_model->is_participant($this->user->selected_assignment['participants'],$this->user->username) )
			$this->data['error'] = 'You cannot submit this assignment right now.';
		else if ($this->user->level < 2 && shj_now() < strtotime($this->user->selected_assignment['start_time']))
			$this->data['error'] = 'Selected assignment has not started.';
		else if ($this->user->level == 0 && ! $this->user->selected_assignment['open'])
			$this->data['error'] = 'Selected assignment is closed.';
		else if ($this->user->selected_assignment['forever'] == 0)
			if ($this->user->level == 0 && shj_now() > strtotime($this->user->selected_assignment['finish_time'])+$this->user->selected_assignment['extra_time']) // deadline = finish_time + extra_time
				$this->data['error'] = 'Selected assignment has finished.';
			else
				$this->data['error'] = 'none';
		else //if ($this->user->selected_assignment['forever'] == 1)
			$this->data['error'] = 'none';

//		if ($this->user->level > 0)
//			$this->data['error'] = 'none';

        if ($mode == 'file')
		$this->twig->display('pages/submit.twig', $this->data);
        else if ($mode == 'editor')
        {
            //$this->data['code_txt'] = $code;
            $this->twig->display('pages/editor.twig', $this->data);
        }

	}


	// ------------------------------------------------------------------------


	/**
	 * Saves submitted code and adds it to queue for judging
	 */
	private function _upload($mode = 'file')
	{
		$now = shj_now();
		foreach($this->problems as $item)
			if ($item['id'] == $this->input->post('problem'))
			{
				$this->problem = $item;
				break;
			}
		$this->filetype = $this->_language_to_type(strtolower(trim($this->input->post('language'))));
		if ($mode == 'file')
		{
			$this->ext = substr(strrchr($_FILES['userfile']['name'],'.'),1); // uploaded file extension
			$this->file_name = basename($_FILES['userfile']['name'], ".{$this->ext}"); // uploaded file name without extension
		}
		else if ($mode == 'editor')
		{
		    $this->ext = $this->_language_to_ext(strtolower(trim($this->input->post('language'))));
		}
		if ( $this->queue_model->in_queue($this->user->username,$this->user->selected_assignment['id'], $this->problem['id']) )
			show_error('You have already submitted for this problem. Your last submission is still in queue.');
		if ($this->user->level==0 && !$this->user->selected_assignment['open'])
			show_error('Selected assignment has been closed.');
		if ($this->user->level==0 && $now < strtotime($this->user->selected_assignment['start_time']))
			show_error('Selected assignment has not started.');
		if ($this->user->selected_assignment['forever'] == 0)
		{
			if ($this->user->level==0 && $now > strtotime($this->user->selected_assignment['finish_time'])+$this->user->selected_assignment['extra_time'])
				show_error('Selected assignment has finished.');
		}
		if ($this->user->level==0 &&  ! $this->assignment_model->is_participant($this->user->selected_assignment['participants'],$this->user->username) )
			show_error('You are not registered for submitting.');
		
		if ($mode == 'file')
		{
		$filetypes = explode(",",$this->problem['allowed_languages']);
		foreach ($filetypes as &$filetype)
		{
			$filetype = $this->_language_to_type(strtolower(trim($filetype)));
		}
		if ($_FILES['userfile']['error'] == 4)
			show_error('No file chosen.');
		if ( ! in_array($this->filetype, $filetypes))
			show_error('This file type is not allowed for this problem.');
		if ( ! $this->_match($this->filetype, $this->ext) )
			show_error('This file type does not match your selected language.');
		if ( ! preg_match('/^[a-zA-Z0-9_\-()]+$/', $this->file_name) )
			show_error('Invalid characters in file name.');
		}

		$user_dir = rtrim($this->assignment_root, '/').'/assignment_'.$this->user->selected_assignment['id'].'/p'.$this->problem['id'].'/'.$this->user->username;
		if ( ! file_exists($user_dir))
			mkdir($user_dir, 0700);

        if ($mode == 'file')
        {
			$config['upload_path'] = $user_dir;
			$config['allowed_types'] = '*';
			$config['max_size']	= $this->settings_model->get_setting('file_size_limit');
			$config['file_name'] = $this->file_name."-".($this->user->selected_assignment['total_submits']+1).".".$this->ext;
			$config['max_file_name'] = 20;
			$config['remove_spaces'] = TRUE;
			$this->upload->initialize($config);

		    if(! $this->upload->do_upload('userfile'))
		        return FALSE;
		    else
		        $result = $this->upload->data();
		}
		else if ($mode == 'editor')
		{
		    $this->load->helper('file');
        
            $file_content = $this->input->post('content');
            $file_name = "editor-".($this->user->selected_assignment['total_submits']+1);
            $user_file_path = $user_dir."/".$file_name.".".$this->ext;
            
            if (! write_file($user_file_path, $file_content))
                return FALSE;
		}

		$this->load->model('submit_model');

		$submit_info = array(
			'submit_id' => $this->assignment_model->increase_total_submits($this->user->selected_assignment['id']),
			'username' => $this->user->username,
			'assignment' => $this->user->selected_assignment['id'],
			'problem' => $this->problem['id'],
			'file_type' => $this->filetype,
			'coefficient' => $this->coefficient,
			'pre_score' => 0,
			'time' => shj_now_str(),
		);
		
		if ($mode == 'file')
		{
		    $submit_info['file_name'] = $result['raw_name'];
		    $submit_info['main_file_name'] = $this->file_name;
		}
		else if ($mode == 'editor')
		{
		    $submit_info['file_name'] = $file_name;
		    $submit_info['main_file_name'] = 'editor';
		}
		    
		    
		if ($this->problem['is_upload_only'] == 0)
		{
			$this->queue_model->add_to_queue($submit_info);
			process_the_queue();
		}
		else
		{
			$this->submit_model->add_upload_only($submit_info);
		}

		return TRUE;
	}

/**
	 * Used by ajax request (for fetching content from template.*)
	 */
	public function template()
	{		
		 if ( ! $this->input->is_ajax_request() )
		 	show_404();
		
		if (empty($_POST))
			echo json_encode("NO POST!");

		$this->form_validation->set_rules('assignment', 'Assignment ID', 'required|integer|greater_than[0]');
		$this->form_validation->set_rules('problem', 'Problem', 'required|integer|greater_than[0]');
		//$this->form_validation->set_rules('language', 'Language', 'required|in_list[c,cpp,py2,py,java]');

		if ($this->form_validation->run())
		{
			$done = 0;

			// GET vars
			$assignment_id = $this->input->post('assignment');
			$problem_id = $this->input->post('problem');
			$language = $this->input->post('language');
			$filename = 'template';

			$assignments_root = rtrim($this->settings_model->get_setting('assignments_root'), '/');
			$problem_dir = $assignments_root . '/assignment_' . $assignment_id . '/p' . $problem_id;
			$full_path = $problem_dir . "/" . $filename . "." . $language;

			if (file_exists($full_path))
			{
				$data = file_get_contents($full_path);
				$done = 1;
			}
			else
			{
				$done = 1;
				$data = NULL;
			}
				
			$json_result = array(
				'done' => $done,
				'data' => $data,
			);
		}
		else
			$json_result = array('done' => 0, 'data' => 'Input Error');

		$this->output->set_header('Content-Type: application/json; charset=utf-8');
		echo json_encode($json_result);
	}


}
