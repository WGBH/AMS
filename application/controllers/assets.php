<?php

/**
 * Assets Controller
 * 
 * PHP version 5
 * 
 * @category   AMS
 * @package    CI
 * @subpackage Controller
 * @author     Nouman Tayyab <nouman@geekschicago.com>
 * @license    AMS http://ams.avpreserve.com
 * @version    GIT: <$Id>
 * @link       http://ams.avpreserve.com
 */

/**
 * Assets Class
 *
 * @category   Class
 * @package    CI
 * @subpackage Controller
 * @author     Nouman Tayyab <nouman@geekschicago.com>
 * @license    AMS http://ams.avpreserve.com
 * @link       http://ams.avpreserve.com
 */
class Assets extends MY_Controller
{

	/**
	 * Constructor
	 * 
	 * Load the layout.
	 * 
	 */
	function __construct()
	{
		parent::__construct();
		$this->load->model('manage_asset_model', 'manage_asset');
		$this->load->model('instantiations_model', 'instantiation');
		$this->load->model('assets_model');
	}

	public function edit()
	{
		$asset_id = $this->uri->segment(3);

		if ( ! empty($asset_id))
		{

			if ($this->input->post())
			{
				debug($this->input->post(), FALSE);
				$this->delete_asset_attributes($asset_id);
				if ($this->input->post('asset_type'))
				{
					foreach ($this->input->post('asset_type') as $value)
					{
						$asset_type_d['assets_id'] = $asset_id;
						if ($asset_type = $this->assets_model->get_assets_type_by_type($value))
						{
							$asset_type_d['asset_types_id'] = $asset_type->id;
						}
						else
						{
							$asset_type_d['asset_types_id'] = $this->assets_model->insert_asset_types(array("asset_type" => $value));
						}

						$this->assets_model->insert_assets_asset_types($asset_type_d);
					}
				}
				if ($this->input->post('asset_date'))
				{
					foreach ($this->input->post('asset_date') as $index => $value)
					{
						$asset_date_info['assets_id'] = $asset_id;
						$asset_date_info['asset_date'] = $value;
						$date_type = $this->input->post('asset_date_type');

						if ($asset_date_type = $this->instantiation->get_date_types_by_type($date_type[$index]))
						{
							$asset_date_info['date_types_id'] = $asset_date_type->id;
						}
						else
						{
							$asset_date_info['date_types_id'] = $this->instantiation->insert_date_types(array("date_type" => $date_type[$index]));
						}

						$this->assets_model->insert_asset_date($asset_date_info);
					}
				}
				if ($this->input->post('asset_identifier'))
				{
					foreach ($this->input->post('asset_identifier') as $index => $value)
					{
						if ( ! empty($value))
						{
							$identifier_source = $this->input->post('asset_identifier_source');
							$identifier_ref = $this->input->post('asset_identifier_ref');
							$identifier_detail['assets_id'] = $asset_id;
							$identifier_detail['identifier'] = $value;
							if ( ! empty($identifier_source[$index]))
								$identifier_detail['identifier_source'] = $identifier_source[$index];
							if ( ! empty($identifier_ref[$index]))
								$identifier_detail['identifier_ref'] = $identifier_ref[$index];
							$this->assets_model->insert_identifiers($identifier_detail);
						}
					}
				}
				if ($this->input->post('asset_title'))
				{
					foreach ($this->input->post('asset_title') as $index => $value)
					{
						$title_type = $this->input->post('asset_title_type');
						$title_source = $this->input->post('asset_title_source');
						$title_ref = $this->input->post('asset_title_ref');
						if ( ! empty($value))
						{
							$title_detail['assets_id'] = $asset_id;
							$title_detail['title'] = $value;
							if ($title_type[$index])
							{
								$asset_title_types = $this->assets_model->get_asset_title_types_by_title_type($title_type[$index]);
								if (isset($asset_title_types) && isset($asset_title_types->id))
								{
									$asset_title_types_id = $asset_title_types->id;
								}
								else
								{
									$asset_title_types_id = $this->assets_model->insert_asset_title_types(array("title_type" => $title_type[$index]));
								}
								$title_detail['asset_title_types_id'] = $asset_title_types_id;
							}
							if ($title_ref[$index])
							{
								$title_detail['title_ref'] = $title_ref[$index];
							}
							if ($title_source[$index])
							{
								$title_detail['title_source'] = $title_source[$index];
							}
							$title_detail['created'] = date('Y-m-d H:i:s');
							$title_detail['updated'] = date('Y-m-d H:i:s');
							$this->assets_model->insert_asset_titles($title_detail);
						}
					}
				}
				if ($this->input->post('asset_subject'))
				{
					foreach ($this->input->post('asset_subject') as $index => $value)
					{
						$subject_type = $this->input->post('asset_subject_type');
						$subject_source = $this->input->post('asset_subject_source');
						$subject_ref = $this->input->post('asset_subject_ref');
						if ( ! empty($value))
						{
							$subject_detail['assets_id'] = $asset_id;

							$subject_d = array();
							$subject_d['subject'] = $value;
							$subject_d['subjects_types_id'] = $subject_type[$index];
							if ( ! empty($subject_ref[$index]))
							{
								$subject_d['subject_ref'] = $subject_ref[$index];
							}
							if ( ! empty($subject_source[$index]))
							{
								$subject_d['subject_source'] = $subject_source[$index];
							}

							$subject_id = $this->assets_model->insert_subjects($subject_d);



							$subject_detail['subjects_id'] = $subject_id;
							$assets_subject_id = $this->assets_model->insert_assets_subjects($subject_detail);
						}
					}
				}
				if ($this->input->post('asset_description'))
				{
					$desc_type = $this->input->post('asset_description_type');
					foreach ($this->input->post('asset_description') as $index => $value)
					{
						if ( ! empty($value))
						{
							$asset_descriptions_d['assets_id'] = $asset_id;
							$asset_descriptions_d['description'] = $value;
							$asset_description_type = $this->assets_model->get_description_by_type($desc_type[$index]);
							if (isset($asset_description_type) && isset($asset_description_type->id))
							{
								$asset_description_types_id = $asset_description_type->id;
							}
							else
							{
								$asset_description_types_id = $this->assets_model->insert_description_types(array("description_type" => $desc_type[$index]));
							}
							$asset_descriptions_d['description_types_id'] = $asset_title_types_id;
							$this->assets_model->insert_asset_descriptions($asset_descriptions_d);
						}
					}
				}
				exit;
			}
			$data['asset_detail'] = $this->manage_asset->get_asset_detail_by_id($asset_id);
debug($data['asset_detail']);
			if ($data['asset_detail'])
			{
				$data['asset_id'] = $asset_id;
				$data['list_assets'] = $this->instantiation->get_instantiations_by_asset_id($asset_id);
				$data['pbcore_asset_types'] = $this->manage_asset->get_picklist_values(1);
				$data['pbcore_asset_date_types'] = $this->manage_asset->get_picklist_values(2);
				$data['pbcore_asset_title_types'] = $this->manage_asset->get_picklist_values(3);
				$data['pbcore_asset_subject_types'] = $this->manage_asset->get_subject_types();
				$data['pbcore_asset_description_types'] = $this->manage_asset->get_picklist_values(4);
				$data['pbcore_asset_audience_level'] = $this->manage_asset->get_picklist_values(5);
				$data['pbcore_asset_audience_rating'] = $this->manage_asset->get_picklist_values(6);
				$data['pbcore_asset_relation_types'] = $this->manage_asset->get_picklist_values(7);
				$data['pbcore_asset_creator_roles'] = $this->manage_asset->get_picklist_values(8);
				$data['pbcore_asset_contributor_roles'] = $this->manage_asset->get_picklist_values(9);
				$data['pbcore_asset_publisher_roles'] = $this->manage_asset->get_picklist_values(10);
				$data['organization'] = $this->station_model->get_all();
				$this->load->view('assets/edit', $data);
			}
			else
			{
				show_error('Not a valid asset id');
			}
		}
		else
		{
			show_error('Require asset id for editing');
		}
	}

	private function delete_asset_attributes($asset_id)
	{
		$this->manage_asset->delete_asset_types($asset_id);
		$this->manage_asset->delete_asset_dates($asset_id);
		$this->manage_asset->delete_local_identifiers($asset_id);
		$this->manage_asset->delete_asset_titles($asset_id);
		$this->manage_asset->delete_asset_subjects($asset_id);
		$this->manage_asset->delete_asset_descriptions($asset_id);
		return TRUE;


		
		$this->manage_asset->delete_asset_genre($asset_id);
		$this->manage_asset->delete_asset_coverage($asset_id);
		$this->manage_asset->delete_audience_level($asset_id);
		$this->manage_asset->delete_audience_rating($asset_id);
		$this->manage_asset->delete_audience_annotations($asset_id);
		$this->manage_asset->delete_audience_relations($asset_id);
		$this->manage_asset->delete_creator($asset_id);
		$this->manage_asset->delete_contributor($asset_id);
		$this->manage_asset->delete_publisher($asset_id);
		$this->manage_asset->delete_rights($asset_id);
	}

	public function insert_pbcore_values()
	{
		$asset_type = array('Copyright Holder', 'Distributor', 'Presenter', 'Publisher', 'Release Agent'
		);
		foreach ($asset_type as $value)
		{
//			$this->manage_asset->insert_picklist_value(array('element_type_id' => 10, 'value' => $value));
		}
	}

}