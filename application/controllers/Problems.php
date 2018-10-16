<?php
/**
 * Sharif Judge online judge
 * @file Problems.php
 * @author Mohammad Javad Naderi <mjnaderi@gmail.com>
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Problems extends CI_Controller
{

	private $all_assignments;


	// ------------------------------------------------------------------------


	public function __construct()
	{
		parent::__construct();
		if ( ! $this->session->userdata('logged_in')) // if not logged in
			redirect('login');

		$this->all_assignments = $this->assignment_model->all_assignments();
	}


	// ------------------------------------------------------------------------


	/**
	 * Displays detail description of given problem
	 *
	 * @param int $assignment_id
	 * @param int $problem_id
	 */
	public function index($assignment_id = NULL, $problem_id = 0)
	{

		// If no assignment is given, use selected assignment
		if ($assignment_id === NULL)
			$assignment_id = $this->user->selected_assignment['id'];
		if ($assignment_id == 0)
			show_error('No assignment selected.');

		$assignment = $this->assignment_model->assignment_info($assignment_id);

		$data = array(
			'all_assignments' => $this->all_assignments,
			'all_problems' => $this->assignment_model->all_problems($assignment_id, 0, true),
			'description_assignment' => $assignment,
			'can_submit' => TRUE,
			'can_view' => TRUE,
			'error_txt' => '',
		);

		$this->load->model('submit_model');
		
		// LEVEL mode
		if ($assignment['level_mode'] == 1 && $this->user->level == 0)
		{
		    $level = $this->assignment_model->get_current_level($assignment_id, $this->user->username);
			$data['user_problems'] = $this->submit_model->all_problems_score($assignment_id, $level);
		}
		else
		{
		    $level = 0;
			$data['user_problems'] = $this->submit_model->all_problems_score($assignment_id, 0, true);
		}

		// For student, set $problem_id to one of first problem they didn't complete
		if ( $this->user->level == 0 && $problem_id == 0 )
			foreach ($data['user_problems'] as $problem)
			{
				$problem_id = $problem['id'];
				if ( $problem['pre_score'] != 10000)
					break;
			}
		else if ( $problem_id == 0 )
			$problem_id = 1;

		if ( ! is_numeric($problem_id) || $problem_id < 1)
			show_404();

		if ( $assignment['id'] == 0 ) {
			$data['can_view'] = FALSE;
			$data['error_txt'] = 'Problem does not exist.';
			$data['can_submit'] = FALSE;
		} else if ( ($this->user->level < 2) && ! ($this->assignment_model->is_participant($assignment['participants'], $this->user->username)) ) {
			$data['can_view'] = FALSE;
			$data['error_txt'] = 'Problem does not exist.';
			$data['can_submit'] = FALSE;
		} else if ($this->user->level < 2 && shj_now() < strtotime($assignment['start_time']) ) {
			if ( $assignment['hide_before_start'] == 1 )
				$data['error_txt'] = 'Problem does not exist.';
			else
				$data['error_txt'] = 'Please wait until this assignment starts.';
			$data['can_view'] = FALSE;
			$data['can_submit'] = FALSE;		
		} else if ( ($this->user->level == 0) && ! ($assignment['open']) ) {
			$data['can_view'] = FALSE;
			$data['error_txt'] = 'This assignment has been closed.';
			$data['can_submit'] = FALSE;
		} else if ( $assignment['forever'] == 0 ) {
			if (($this->user->level == 0) && shj_now() > strtotime($assignment['finish_time'])+$assignment['extra_time'] && $assignment['open'])
			{
				$data['can_submit'] = FALSE;
			}
		} else if ( $problem_id > $data['description_assignment']['problems'] ) {
			$data['can_view'] = FALSE;
			$data['error_txt'] = 'Problem does not exist.';
			$data['can_submit'] = FALSE;
		} else if ( ($this->user->level == 0) && $assignment['level_mode'] == 1 && $data['all_problems'][$problem_id]['level'] > $level) {
		    $data['can_view'] = FALSE;
			$data['error_txt'] = 'Problem does not exist.';
			$data['can_submit'] = FALSE;
		}

		if ( $assignment_id != $this->user->selected_assignment['id'] )
			$data['can_submit'] = FALSE;

//		if ( $this->user->level > 0 )
//			$data['can_submit'] = TRUE;

		if ( $data['can_view'] == TRUE )
		{
			$languages = explode(',',$data['user_problems'][$problem_id]['allowed_languages']);

			$assignments_root = rtrim($this->settings_model->get_setting('assignments_root'),'/');
			$problem_dir = "$assignments_root/assignment_{$assignment_id}/p{$problem_id}";
			$data['problem'] = array(
				'id' => $problem_id,
				'description' => '<p>Description not found</p>',
				'allowed_languages' => $languages,
				'has_pdf' => glob("$problem_dir/*.pdf") != FALSE
			);

			$path = "$problem_dir/desc.html";
			if (file_exists($path))
				$data['problem']['description'] = file_get_contents($path);
		}

		$this->twig->display('pages/problems.twig', $data);
	}


	// ------------------------------------------------------------------------


	/**
	 * Edit problem description as html/markdown
	 *
	 * $type can be 'md', 'html', or 'plain'
	 *
	 * @param string $type
	 * @param int $assignment_id
	 * @param int $problem_id
	 */
	public function edit($type = 'md', $assignment_id = NULL, $problem_id = 1)
	{
		if ($type !== 'html' && $type !== 'md' && $type !== 'plain')
			show_404();

		if ($this->user->level <= 1)
			show_404();

		switch($type)
		{
			case 'html':
				$ext = 'html'; break;
			case 'md':
				$ext = 'md'; break;
			case 'plain':
				$ext = 'html'; break;
		}

		if ($assignment_id === NULL)
			$assignment_id = $this->user->selected_assignment['id'];
		if ($assignment_id == 0)
			show_error('No assignment selected.');

		$data = array(
			'all_assignments' => $this->assignment_model->all_assignments(),
			'description_assignment' => $this->assignment_model->assignment_info($assignment_id),
			'problem_info' => $this->assignment_model->problem_info($assignment_id, $problem_id),
		);

		if ( ! is_numeric($problem_id) || $problem_id < 1 || $problem_id > $data['description_assignment']['problems'])
			show_404();

		$this->form_validation->set_rules('text', 'text' ,''); /* todo: xss clean */
		if ($this->form_validation->run())
		{
			$this->assignment_model->save_problem_description($assignment_id, $problem_id, $this->input->post('text'), $ext);
			redirect('problems/'.$assignment_id.'/'.$problem_id);
		}

		$data['problem'] = array(
			'id' => $problem_id,
			'description' => ''
		);

		$path = rtrim($this->settings_model->get_setting('assignments_root'),'/')."/assignment_{$assignment_id}/p{$problem_id}/desc.".$ext;
		if (file_exists($path))
			$data['problem']['description'] = file_get_contents($path);


		$this->twig->display('pages/admin/edit_problem_'.$type.'.twig', $data);

	}


}
