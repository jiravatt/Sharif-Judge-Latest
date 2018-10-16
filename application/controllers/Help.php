<?php
/**
 * Sharif Judge online judge
 * @file Hroblems.php
 * @author WYSIWYG <jiravatt.r@gmail.com>
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Help extends CI_Controller
{

	public function __construct()
	{
		parent::__construct();
		if ( ! $this->session->userdata('logged_in')) // if not logged in
			redirect('login');
		
		$this->all_assignments = $this->assignment_model->all_assignments();

		$this->load->model('help_model');
	}

	// ------------------------------------------------------------------------

	public function index()
	{
		$data['help_txt'] = $this->help_model->get_help_text('html');
		$data = array(
			'help_txt' => $this->help_model->get_help_text('html'),
			'all_assignments' => $this->all_assignments,
		);
		$this->twig->display('pages/help.twig', $data);
	}

	// ------------------------------------------------------------------------

	public function edit()
	{
		if ($this->user->level <= 1)
			show_404();

		$this->form_validation->set_rules('text', 'text' ,'required');
		if ($this->form_validation->run())
		{
			$this->help_model->save_help_text($this->input->post('text'));
			redirect('help');
		}
		$data = array(
			'help_md' => $this->help_model->get_help_text('md'),
			'all_assignments' => $this->all_assignments,
		);

		$this->twig->display('pages/admin/edit_help.twig', $data);
	}

}