<?php

/**
* Settings controller.
*
* @package    AMS
* @subpackage Scheduled Tasks
* @author     Ali Raza
*/
class Crons extends CI_Controller
{

	/**
	*
	* constructor. Load layout,Model,Library and helpers
	* 
	*/
	public $assets_path;
	
	function __construct()
	{
		parent::__construct();
		$this->load->model('email_template_model', 'email_template');
		$this->load->model('cron_model');
		$this->load->model('assets_model');
		$this->load->model('instantiations_model','instant');
		$this->load->model('essence_track_model','essence');
		$this->load->model('station_model');
		$this->assets_path = 'assets/';
	}
	
	/**
	* Process all pending email 
	*  
	*/
	function processemailqueues()
	{
		$email_queue = $this->email_template->get_all_pending_email();
		foreach ($email_queue as $queue)
		{
			$now_queue_body = $queue->email_body . '<img src="' . site_url('emailtracking/' . $queue->id . '.png') . '" height="1" width="1" />';
			if (send_email($queue->email_to, $queue->email_from, $queue->email_subject, $now_queue_body))
			{
				$this->email_template->update_email_queue_by_id($queue->id, array("is_sent" => 2, "sent_at" => date('Y-m-d H:i:s')));
				echo "Email Sent To " . $queue->email_to . " <br/>";
			}
		}
	}
	
	/**
	* Store All Assets Data Files Structure in database
	*  
	*/
	function process_dir()
	{
		set_time_limit(0);
		$this->cron_model->scan_directory($this->assets_path, 'assets');
		echo "All Data Path Under {$this->assets_path} Directory Stored ";
		exit(0);
	}
	
	/**
	* Process all pending assets Data Files
	*
	2:// now we get station_id and store in assets table and get asset_id
	
	
	
	xml procssing start from here
	3. not get asset_type in pbcore.xml ( So there will be no entry in assets_asset_types{look up} ,asset_dates,date_types {look up} )
	4. for American Archive GUID pbcoreidentifier (field identifier) and store in identifiers
	5 assets_titles store(title and asset_title_types_id from refrence table) asset_title_types(Store titletype from xml {look up}) titale_source and title_ref not available in 1.3
	6. subjects and assets_subjects (If available )
	7.asset_description, description_types {look up}
	8 genres (Store info from xml and its id and asset_id store in asset_genres)
	9 coverage and coverage type  not required fiedls
	10 audience_levels(Store info from xml and its id and asset_id store in assets_audience_levels)
	11  relation_types(Store info from xml and its id and asset_id store in assets_relations)
	12  creators and creator_role(store info from xml and) assets_creators_roles(save creator_id,creater_role_id and asset_id)
	13 contributors and contributor_roles(store info from xml and) assets_contributors_roles (save contributors_id,contributor_roles_id and asset_id)
	14 publishers and publishers_roles(store info from xml and) assets_publishers_role(save assets_id,publishers_id and asset_id)
	15  nomination_status {lookup} nominations(store data from xml)
	*/
	function process_xml_file()
	{
		$folders = $this->cron_model->get_all_data_folder();
		if(isset($folders) && !empty($folders))
		{
			foreach ($folders as $folder)
			{
				$data = file_get_contents($folder->folder_path . 'data/organization.xml');
				$x = @simplexml_load_string($data);
				$data = xmlObjToArr($x);
				$station_cpb_id = $data['children']['cpb-id'][0]['text'];
				if (isset($station_cpb_id))
				{
					$station_data = $this->station_model->get_station_by_cpb_id($station_cpb_id);
					if (isset($station_data) && !empty($station_data) && isset($station_data->id))
					{
						
						$data_files = $this->cron_model->get_pbcore_file_by_folder_id($folder->id);
						if (isset($data_files))
						{
							foreach ($data_files as $d_file)
							{
								if ($d_file->is_processed == 0)
								{
									$file_path = '';
									$file_path = trim($folder->folder_path . $d_file->file_path);
									if (is_file($file_path))
									{
										//$file_parts = pathinfo($file_path);
										/*if (!isset($file_parts['extension']))
										{
											$server_root_path = trim(shell_exec('pwd'));
											$src = ($server_root_path . '/' . $file_path);
											$des = ($server_root_path . '/' . $file_path . '.xml');
											copy($src, $des);
										}*/
										echo "Currently Parsing Files ".$file_path."\n";
										$asset_data = @file_get_contents($file_path );
										if (isset($asset_data) && !empty($asset_data))
										{
											$asset_xml_data = @simplexml_load_string($asset_data);
											$asset_d = xmlObjToArr($asset_xml_data);
											$asset_id=$this->assets_model->insert_assets(array("stations_id"=>$station_data->id,"created"=>date("Y-m-d H:i:s")));
											echo "Current Version " .$asset_d['attributes']['version']." \n ";

											if (!isset($asset_d['attributes']['version']) || empty($asset_d['attributes']['version']) || $asset_d['attributes']['version'] == '1.3')
											{
												echo "\n in Process \n";
												$asset_children = $asset_d['children'];
												if(isset($asset_children))
												{
													// Instantiation Start
													$this->process_assets($asset_children,$asset_id);
													// Instantiation End
													
													// Instantiation Start
													$this->process_instantiation($asset_children,$asset_id);
													// Instantiation End
												}
												unset($asset_d);
												unset($asset_xml_data);
												unset($asset_data);
												$this->db->where('id',$d_file->id);
												$this->db->update('process_pbcore_data',array('is_processed'=>1));
												echo $this->db->last_query();
												echo "<br/>\n<br/>";
											}
										
										
											
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
	/*
	* Process Instantiation Elements
	*/
	function process_instantiation($asset_children,$asset_id)
	{
		// pbcoreAssetType Start here
		if (isset($asset_children['pbcoreinstantiation']))
		{
			foreach ($asset_children['pbcoreinstantiation'] as $pbcoreinstantiation)
			{
				if(isset($pbcoreinstantiation['children']) && !empty($pbcoreinstantiation['children']))
				{
					
					$pbcoreinstantiation_child=$pbcoreinstantiation['children'];
					//pbcoreInstantiation Start
					$instantiations_d=array();
					$instantiations_d['assets_id']=$asset_id;
					//Instantiation formatLocation
					if(isset($pbcoreinstantiation_child['formatlocation']))
					{
						if(isset($pbcoreinstantiation_child['formatlocation'][0]['text']))
						{
							$instantiations_d['location']=$pbcoreinstantiation_child['formatlocation'][0]['text'];
						}
						
					}
					
					//Instantiation formatMediaType
					if(isset($pbcoreinstantiation_child['formatmediatype'][0]['text']))
					{
						$inst_media_type=$this->instant->get_instantiation_media_types_by_media_type($pbcoreinstantiation_child['formatmediatype'][0]['text']);
						if($inst_media_type)
						{
							$instantiations_d['instantiation_media_type_id']=$inst_media_type->id;
						}
						else
						{
							$instantiations_d['instantiation_media_type_id']=$this->instant->insert_instantiation_media_types(array("media_type"=>$pbcoreinstantiation_child['formatmediatype'][0]['text']));
							
						}
					}
					
					//Instantiation formatFileSize Start
					if(isset($pbcoreinstantiation_child['formatfilesize'][0]['text']))
					{
						$files_size_perm=explode(" ",$pbcoreinstantiation_child['formatfilesize'][0]['text']);
						if(isset($files_size_perm[0]))
						{
							$instantiations_d['file_size']=$files_size_perm[0];
						}
						if(isset($files_size_perm[1]))
						{
							$instantiations_d['file_size_unit_of_measure']=$files_size_perm[1];
						}
						
					}
					
					//Instantiation formatTimeStart Start
					if(isset($pbcoreinstantiation_child['formattimestart'][0]['text']))
					{
						$instantiations_d['time_start']=$pbcoreinstantiation_child['formattimestart'][0]['text'];
					}
					
					//Instantiation formatDuration Start
					if(isset($pbcoreinstantiation_child['formatduration'][0]['text']))
					{
						$instantiations_d['projected_duration']=$pbcoreinstantiation_child['formatduration'][0]['text'];
					}
					
					//Instantiation formatDataRate Start
					if(isset($pbcoreinstantiation_child['formatdatarate'][0]['text']))
					{
						$format_data_rate_perm=explode(" ",$pbcoreinstantiation_child['formatdatarate'][0]['text']);
						if(isset($format_data_rate_perm[0]))
						{
							$instantiations_d['data_rate']=$format_data_rate_perm[0];
						}
						if(isset($format_data_rate_perm[1]))
						{
							$data_rate_unit_d=$this->instant->get_data_rate_units_by_unit($format_data_rate_perm[1]);
							if($data_rate_unit_d)
							{
								$instantiations_d['data_rate_units_id']=$data_rate_unit_d->id;
							}
							else
							{
								$instantiations_d['data_rate_units_id']=$this->instant->insert_data_rate_units(array("unit_of_measure"=>$format_data_rate_perm[1]));
							}
						}
						
					}
					
					//Instantiation formatcolors Start
					if(isset($pbcoreinstantiation_child['formatcolors'][0]['text']))
					{
						$inst_color_d=$this->instant->get_instantiation_colors_by_color($pbcoreinstantiation_child['formatcolors'][0]['text']);
						if($inst_color_d)
						{
							$instantiations_d['instantiation_colors_id']=$inst_color_d->id;
						}
						else
						{
							$instantiations_d['instantiation_colors_id']=$this->instant->insert_instantiation_colors(array('color'=>$pbcoreinstantiation_child['formatcolors'][0]['text']));	
						}
					}
					
					//Instantiation formattracks Start
					if(isset($pbcoreinstantiation_child['formattracks'][0]['text']))
					{
						$instantiations_d['tracks']=$pbcoreinstantiation_child['formattracks'][0]['text'];
					}
					
					//Instantiation formatchannelconfiguration Start
					if(isset($pbcoreinstantiation_child['formatchannelconfiguration'][0]['text']))
					{
						$instantiations_d['channel_configuration']=$pbcoreinstantiation_child['formatchannelconfiguration'][0]['text'];
					}
					
					//Instantiation language Start
					if(isset($pbcoreinstantiation_child['language'][0]['text']))
					{
						$instantiations_d['language']=$pbcoreinstantiation_child['language'][0]['text'];
					}
					
					//Instantiation alternativemodes Start
					if(isset($pbcoreinstantiation_child['alternativemodes'][0]['text']))
					{
						$instantiations_d['alternative_modes']=$pbcoreinstantiation_child['alternativemodes'][0]['text'];
					}
					
					$instantiations_id=$this->instant->insert_instantiations($instantiations_d);
					
					//pbcoreInstantiation End
					
					
					
					
					
					if(isset($pbcoreinstantiation_child['pbcoreformatid']))
					{
						
						foreach($pbcoreinstantiation_child['pbcoreformatid'] as $pbcoreformatid)
						{
							$instantiation_identifier_d=array();
							$instantiation_identifier_d['instantiations_id']=$instantiations_id;
							if(isset($pbcoreformatid['children']) && !empty($pbcoreformatid['children']))
							{
								if($pbcoreformatid['children']['formatidentifier'][0]['text'])
								{
									$instantiation_identifier_d['instantiation_identifier']=$pbcoreformatid['children']['formatidentifier'][0]['text'];
								}
								if($pbcoreformatid['children']['formatidentifiersource'][0]['text'])
								{
									$instantiation_identifier_d['instantiation_source']=$pbcoreformatid['children']['formatidentifiersource'][0]['text'];
								}
								//print_r($instantiation_identifier_d);
								$instantiation_identifier_id=$this->instant->insert_instantiation_identifier($instantiation_identifier_d);
							}
						}
					}
					//Instantiation Date Created Start
					if(isset($pbcoreinstantiation_child['datecreated']))
					{
						$instantiation_dates_d=array();
						$instantiation_dates_d['instantiations_id']=$instantiations_id;
						
						if(isset($pbcoreinstantiation_child['datecreated'][0]['text']))
						{
							$instantiation_dates_d['instantiation_date']=$pbcoreinstantiation_child['datecreated'][0]['text'];
							$date_type=$this->instant->get_date_types_by_type('created');
							if($date_type)
							{
								$instantiation_dates_d['date_types_id']=$date_type->id;
							}
							else
							{
								$instantiation_dates_d['date_types_id']=$this->instant->insert_date_types(array('date_type'=>'created'));
							}
							$instantiation_date_created_id=$this->instant->insert_instantiation_dates($instantiation_dates_d);
						}
						
					}
					//Instantiation Date Created End
					
					//Instantiation Date Issued Start
					if(isset($pbcoreinstantiation_child['dateissued']))
					{
						$instantiation_dates_d=array();
						$instantiation_dates_d['instantiations_id']=$instantiations_id;
						
						if(isset($pbcoreinstantiation_child['dateissued'][0]['text']))
						{
							$instantiation_dates_d['instantiation_date']=$pbcoreinstantiation_child['dateissued'][0]['text'];
							$date_type=$this->instant->get_date_types_by_type('issued');
							if($date_type)
							{
								$instantiation_dates_d['date_types_id']=$date_type->id;
							}
							else
							{
								$instantiation_dates_d['date_types_id']=$this->instant->insert_date_types(array('date_type'=>'issued'));
							}
							$instantiation_date_issued_id=$this->instant->insert_instantiation_dates($instantiation_dates_d);
						}
						
					}
					//Instantiation Date Issued End
					
					//Instantiation formatPhysical  Start
					if(isset($pbcoreinstantiation_child['formatphysical'][0]['text']))
					{
						$instantiation_format_physical_d=array();
						$instantiation_format_physical_d['instantiations_id']=$instantiations_id;
						$instantiation_format_physical_d['format_name']=$pbcoreinstantiation_child['formatphysical'][0]['text'];
						$instantiation_format_physical_d['format_type']='physical';
						$instantiation_format_physical_id=$this->instant->insert_instantiation_formats($instantiation_format_physical_d);
						
					}
					
					//Instantiation formatdigital  Start
					if(isset($pbcoreinstantiation_child['formatdigital'][0]['text']))
					{
						$instantiation_format_digital_d=array();
						$instantiation_format_digital_d['instantiations_id']=$instantiations_id;
						$instantiation_format_digital_d['format_name']=$pbcoreinstantiation_child['formatdigital'][0]['text'];
						$instantiation_format_digital_d['format_type']='digital';
						$instantiation_format_digital_id=$this->instant->insert_instantiation_formats($instantiation_format_digital_d);
						
					}
					
					//Instantiation formatgenerations  Start
					if(isset($pbcoreinstantiation_child['formatgenerations']))
					{
						foreach($pbcoreinstantiation_child['formatgenerations'] as $format_generations)
						{
							if(isset($format_generations['text']) && !empty($format_generations['text']))
							{
								$instantiation_format_generations_d=array();
								$instantiation_format_generations_d['instantiations_id']=$instantiations_id;
								$generations_d=$this->instant->get_generations_by_generation($format_generations['text']);
								if($generations_d)
								{
									$instantiation_format_generations_d['generations_id']=$generations_d->id;
								}
								else
								{
									$instantiation_format_generations_d['generations_id']=$this->instant->insert_generations(array("generation"=>$format_generations['text']));
								}
								$instantiation_format_generations_ids[]=$this->instant->insert_instantiation_generations($instantiation_format_generations_d);
							}
						}
					}
					//Instantiation pbcoreAnnotation  Start
					if(isset($pbcoreinstantiation_child['pbcoreannotation']))
					{
						foreach($pbcoreinstantiation_child['pbcoreannotation'] as $pbcore_annotation)
						{
							if(isset($pbcore_annotation['children']['annotation'][0]['text']) && !empty($pbcore_annotation['children']['annotation'][0]['text']))
							{
								$instantiation_annotation_d=array();
								$instantiation_annotation_d['instantiations_id']=$instantiations_id;
								$instantiation_annotation_d['annotation']=$pbcore_annotation['children']['annotation'][0]['text'];
								$instantiation_annotation_ids[]=$this->instant->insert_instantiation_annotations($instantiation_annotation_d);
								
							}
						}
					}
					//Instantiation pbcoreAnnotation  Start
					if(isset($pbcoreinstantiation_child['pbcoreessencetrack']))
					{
						foreach($pbcoreinstantiation_child['pbcoreessencetrack'] as $pbcore_essence_track)
						{
							if(isset($pbcore_essence_track['children']) && !empty($pbcore_essence_track['children']))
							{
								$pbcore_essence_child=$pbcore_essence_track['children'];
								$essence_tracks_d=array();
								$essence_tracks_d['instantiations_id']=$instantiations_id;
								//essenceTrackType start
								// Required Fields 1.essencetracktype If this not set then no record enter for essence_track
								if(isset($pbcore_essence_child['essencetracktype'][0]['text']) && !empty($pbcore_essence_child['essencetracktype'][0]['text']))
								{
									$essence_track_type_d=$this->essence->get_essence_track_by_type($pbcore_essence_child['essencetracktype'][0]['text']);
									if($essence_track_type_d)
									{
										$essence_tracks_d['essence_track_types_id']=$essence_track_type_d->id;
									}
									else
									{
										$essence_tracks_d['essence_track_types_id']=$this->essence->insert_essence_track_types(array('essence_track_type'=>$pbcore_essence_child['essencetracktype'][0]['text']));
									}
									//essenceTrackStandard Start
									if(isset($pbcore_essence_child['essencetrackstandard'][0]['text']))
									{
										$essence_tracks_d['standard']=$pbcore_essence_child['essencetrackstandard'][0]['text'];
									}
									//essenceRrackDatarate Start
									if(isset($pbcore_essence_child['essencetrackdatarate'][0]['text']))
									{
										$format_data_rate_perm='';
										$format_data_rate_perm=explode(" ",$pbcore_essence_child['essencetrackdatarate'][0]['text']);
										if(isset($format_data_rate_perm[0]))
										{
											$essence_tracks_d['data_rate']=$format_data_rate_perm[0];
										}
										if(isset($format_data_rate_perm[1]))
										{
											$data_rate_unit_d=$this->instant->get_data_rate_units_by_unit($format_data_rate_perm[1]);
											if($data_rate_unit_d)
											{
												$essence_tracks_d['data_rate_units_id']=$data_rate_unit_d->id;
											}
											else
											{
												$essence_tracks_d['data_rate_units_id']=$this->instant->insert_data_rate_units(array("unit_of_measure"=>$format_data_rate_perm[1]));
											}
										}
										
									}
								
									//essencetrackframerate Start
									if(isset($pbcore_essence_child['essencetrackframerate'][0]['text']))
									{
										$frame_rate=explode(" ",$pbcore_essence_child['essencetrackframerate'][0]['text']);
										$essence_tracks_d['frame_rate']=trim($frame_rate[0]);
									}
									
									//essencetrackframerate Start
									if(isset($pbcore_essence_child['essencetracksamplingrate'][0]['text']))
									{
										$essence_tracks_d['sampling_rate']=$pbcore_essence_child['essencetracksamplingrate'][0]['text'];
									}
									
									//essenceTrackBitDepth Start
									if(isset($pbcore_essence_child['essencetrackbitdepth'][0]['text']))
									{
										$essence_tracks_d['bit_depth']=$pbcore_essence_child['essencetrackbitdepth'][0]['text'];
									}
									
									//essenceTrackBitDepth Start
									if(isset($pbcore_essence_child['essencetrackframesize'][0]['text']))
									{
										$frame_sizes=explode("x",strtolower($pbcore_essence_child['essencetrackframesize'][0]['text']));
										if(isset($frame_sizes[0]) && isset($frame_sizes[1]))
										{
											$track_frame_size_d=$this->essence->get_essence_track_frame_sizes_by_width_height(trim($frame_sizes[0]),trim($frame_sizes[1]));
											if($track_frame_size_d)
											{
												$essence_tracks_d['essence_track_frame_sizes_id']=$track_frame_size_d->id;
											}
											else
											{
												$essence_tracks_d['essence_track_frame_sizes_id']=$this->essence->insert_essence_track_frame_sizes(array("width"=>$frame_sizes[0],"height"=>$frame_sizes[1]));
											}
										}
									}
									
									//essencetrackaspectratio Start
									if(isset($pbcore_essence_child['essencetrackaspectratio'][0]['text']))
									{
										$essence_tracks_d['aspect_ratio']=$pbcore_essence_child['essencetrackaspectratio'][0]['text'];
									}
									
									//essencetracktimestart Start
									if(isset($pbcore_essence_child['essencetracktimestart'][0]['text']))
									{
										$essence_tracks_d['time_start']=$pbcore_essence_child['essencetracktimestart'][0]['text'];
									}
									
									//essencetrackduration Start
									if(isset($pbcore_essence_child['essencetrackduration'][0]['text']))
									{
										$essence_tracks_d['duration']=$pbcore_essence_child['essencetrackduration'][0]['text'];
									}
									
									//essencetracklanguage Start
									if(isset($pbcore_essence_child['essencetracklanguage'][0]['text']))
									{
										$essence_tracks_d['language']=$pbcore_essence_child['essencetracklanguage'][0]['text'];
									}
									
									$essence_tracks_id=$this->essence->insert_essence_tracks($essence_tracks_d);
									
									
									
									//essenceTrackIdentifier Start 
									if(isset($pbcore_essence_child['essencetrackidentifier'][0]['text']) && isset($pbcore_essence_child['essencetrackidentifiersource'][0]['text']))
									{
										$essence_track_identifiers_d=array();
										$essence_track_identifiers_d['essence_tracks_id']=$essence_tracks_id;
										$essence_track_identifiers_d['essence_track_identifiers']=$pbcore_essence_child['essencetrackidentifier'][0]['text'];
										$essence_track_identifiers_d['essence_track_identifier_source']=$pbcore_essence_child['essencetrackidentifiersource'][0]['text'];
										$this->essence->insert_essence_track_identifiers($essence_track_identifiers_d);
										
									}
									//essencetrackstandard Start 
									if(isset($pbcore_essence_child['essencetrackstandard'][0]['text']))
									{
										$essence_track_standard_d=array();
										$essence_track_standard_d['essence_tracks_id']=$essence_tracks_id;
										$essence_track_standard_d['encoding']=$pbcore_essence_child['essencetrackstandard'][0]['text'];
										if(isset($pbcore_essence_child['essencetrackencoding'][0]['text']))
										{
											$essence_track_standard_d['encoding_source']=$pbcore_essence_child['essencetrackencoding'][0]['text'];
										}
										$this->essence->insert_essence_track_encodings($essence_track_identifiers_d);	
									}
									
									//essenceTrackAnnotation Start
									if(isset($pbcore_essence_child['essencetrackannotation']))
									{
										foreach($pbcore_essence_child['essencetrackannotation'] as $trackannotation )
										{
											$essencetrackannotation=array();
											$essencetrackannotation['essence_tracks_id']=$essence_tracks_id;
											$essencetrackannotation['annotation']=$trackannotation['text'];
											$this->essence->insert_essence_track_annotations($essencetrackannotation);
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
	/*
	* Process Assets Elements
	*/
	function process_assets($asset_children,$asset_id)
	{
		// pbcoreAssetType Start here
		if (isset($asset_children['pbcoreassettype']))
		{
			foreach ($asset_children['pbcoreassettype'] as $pbcoreassettype)
			{
				$asset_type_d = array();
				if (isset($pbcoreassettype['text']))
				{
					$asset_type_d['asset_type']=$pbcoreassettype['text'];
					if(!$this->assets_model->get_assets_type_by_type($pbcoreassettype['text']))
					{
						$this->assets_model->insert_asset_types($asset_type_d);
					}
				}
			}
		}
		// pbcoreAssetType End here
		
		// pbcoreidentifier Start here
		if (isset($asset_children['pbcoreidentifier']))
		{
			foreach ($asset_children['pbcoreidentifier'] as $pbcoreidentifier)
			{
				$identifier_d = array();
				//As Identfier is Required and based on identifiersource so apply following checks 
				if (isset($pbcoreidentifier['children']['identifier'][0]['text']) && !empty($pbcoreidentifier['children']['identifier'][0]['text']))
				{
					$identifier_d['assets_id'] = $asset_id;
					$identifier_d['identifier'] = $pbcoreidentifier['children']['identifier'][0]['text'];
					$identifier_d['identifier_source']='';
					if(isset($pbcoreidentifier['children']['identifiersource'][0]['text']) && !empty($pbcoreidentifier['children']['identifiersource'][0]['text']))
					{
						$identifier_d['identifier_source'] = $pbcoreidentifier['children']['identifiersource'][0]['text'];
						$this->assets_model->insert_identifiers($identifier_d);
					}
					//print_r($identifier_d);	
					
				}
			}
		}	
		// pbcoreidentifier End here
		
		// pbcoreTitle Start here
		if (isset($asset_children['pbcoretitle']))
		{
			foreach ($asset_children['pbcoretitle'] as $pbcoretitle)
			{
				$pbcore_title_d = array();
				if (isset($pbcoretitle['children']['title'][0]['text']) && !empty($pbcoretitle['children']['title'][0]['text']))
				{						
					$pbcore_title_d['assets_id'] = $asset_id;
					$pbcore_title_d['title'] = $pbcoretitle['children']['title'][0]['text'];
					// As this Field is not required so this can be empty
					if(isset($pbcoretitle['children']['titletype'][0]['text']) && !empty($pbcoretitle['children']['titletype'][0]['text']))
					{
						$asset_title_types = $this->assets_model->get_asset_title_types_by_title_type($pbcoretitle['children']['titletype'][0]['text']);
						if ($asset_title_types)
						{
							$asset_title_types_id = $asset_title_types->id;
						}
						else
						{
							$asset_title_types_id = $this->assets_model->insert_asset_title_types(array("title_type" => $pbcoretitle['children']['titletype'][0]['text']));
						}
						$pbcore_title_d['asset_title_types_id'] = $asset_title_types_id; 
					}
					$pbcore_title_d['created'] = date('Y-m-d H:i:s');
					//For 2.0 
					// $pbcore_title_d['title_source'] 
					// $pbcore_title_d['title_ref']
					//print_r($pbcore_title_d);	
					$this->assets_model->insert_asset_titles($pbcore_title_d);
				}
			}
		}
		// pbcoreTitle End here
		
		// pbcoreSubject Start here
		if (isset($asset_children['pbcoresubject']))
		{
			foreach ($asset_children['pbcoresubject'] as $pbcore_subject)
			{
				$pbcoreSubject_d = array();
				if (isset($pbcore_subject['children']['subject'][0]))
				{
					$pbcoreSubject_d['assets_id'] = $asset_id;
					if (isset($pbcore_subject['children']['subject'][0]['text']) && !empty($pbcore_subject['children']['subject'][0]['text']))
					{
						$subjects = $this->assets_model->get_subjects_id_by_subject($pbcore_subject['children']['subject'][0]['text']);
						if ($subjects)
						{
							$subject_id = $subjects->id;
						}
						else
						{
							//For 2.0  also add following value in insert array of subject
							//subject_ref
							$subject_d=array();
							$subject_d['subject']=$pbcore_subject['children']['subject'][0]['text'];
							$subject_d['subject_source']='';																
							if(isset($pbcore_subject['children']['subjectauthorityused'][0]['text']) && !empty($pbcore_subject['children']['subjectauthorityused'][0]['text']))
							{
								$subject_d['subject_source']=$pbcore_subject['children']['subjectauthorityused'][0]['text'];
							}
							$subject_id = $this->assets_model->insert_subjects($subject_d);
						}
						$pbcoreSubject_d['subjects_id'] = $subject_id;
						//Add Data into insert_assets_subjects
						$assets_subject_id = $this->assets_model->insert_assets_subjects($pbcoreSubject_d);
					}
				}
			}
		}
		// pbcoreSubject End here
		
		// pbcoreDescription Start here
		if (isset($asset_children['pbcoredescription']))
		{
			foreach ($asset_children['pbcoredescription'] as $pbcore_description)
			{
				$asset_descriptions_d = array();
				if (isset($pbcore_description['children']['description'][0]['text']) && !empty($pbcore_description['children']['description'][0]['text']))
				{
					$asset_descriptions_d['assets_id'] = $asset_id;
					$asset_descriptions_d['description'] = $pbcore_description['children']['description'][0]['text'];
					if (isset($pbcoretitle['children']['descriptiontype'][0]['text']) && !empty($pbcoretitle['children']['descriptiontype'][0]['text']))
					{
						$asset_description_type = $this->assets_model->get_description_by_type($pbcoretitle['children']['descriptiontype'][0]['text']);
						if ($asset_description_type)
						{
							$asset_description_types_id = $asset_description_type->id;
						}
						else
						{
							$asset_description_types_id = $this->assets_model->insert_asset_title_types(array("description_type" => $pbcoretitle['children']['descriptiontype'][0]['text']));
						}
						$asset_descriptions_d['description_types_id'] = $asset_title_types_id;
					}
					// Insert Data into asset_description
					//print_r($asset_descriptions_d);
					$this->assets_model->insert_asset_descriptions($asset_descriptions_d);
				}
			}
		}
		// pbcoreDescription End here
		
		// Nouman Tayyab
		
		// pbcoreGenre Start
		if (isset($asset_children['pbcoregenre']))
		{
			foreach ($asset_children['pbcoregenre'] as $pbcore_genre)
			{
				$asset_genre_d = array();
				$asset_genre = array();
				$asset_genre['assets_id'] = $asset_id;
				if (isset($pbcore_genre['children']['genre'][0]) && !empty($pbcore_genre['children']['genre'][0]['text']))
				{
				
					$asset_genre_d['genre'] = $pbcore_genre['children']['genre'][0]['text'];
					$asset_genre_type = $this->assets_model->get_genre_type($asset_genre_d['genre']);
					if ($asset_genre_type)
					{
						$asset_genre['genres_id'] = $asset_genre_type->id;
					}
					else
					{
						if (isset($pbcore_genre['children']['genreauthorityused'][0]))
						{
							$asset_genre_d['genre_source'] = $pbcore_genre['children']['genreauthorityused'][0]['text'];
						}
						$asset_genre_id = $this->assets_model->insert_genre($asset_genre_d);
						$asset_genre['genres_id'] = $asset_genre_id;
					}
					$this->assets_model->insert_asset_genre($asset_genre);
				}
			}
		}
		// pbcoreGenre End
		
		// pbcoreCoverage Start
		if (isset($asset_children['pbcorecoverage']))
		{
			foreach ($asset_children['pbcorecoverage'] as $pbcore_coverage)
			{
				$coverage = array();
				$coverage['assets_id'] = $asset_id;
				if (isset($pbcore_coverage['children']['coverage'][0]) && !empty($pbcore_coverage['children']['coverage'][0]['text']))
				{
					$coverage['coverage'] = $pbcore_coverage['children']['coverage'][0]['text'];
					if (isset($pbcore_coverage['children']['coveragetype'][0]))
					{
						$coverage['coverage_type'] = $pbcore_coverage['children']['coveragetype'][0]['text'];
					}
					$asset_coverage = $this->assets_model->insert_coverage($coverage);
				}
			}
		}
		// pbcoreCoverage End
		
		// pbcoreAudienceLevel Start
		if (isset($asset_children['pbcoreaudiencelevel']))
		{
			foreach ($asset_children['pbcoreaudiencelevel'] as $pbcore_aud_level)
			{
				$audience_level = array();
				$asset_audience_level = array();
				$asset_audience_level['assets_id'] = $asset_id;
				if (isset($pbcore_aud_level['children']['audiencelevel'][0]) && !empty($pbcore_aud_level['children']['audiencelevel'][0]['text']))
				{
					$audience_level['audience_level'] = $pbcore_aud_level['children']['audiencelevel'][0]['text'];
					$db_audience_level = $this->assets_model->get_audience_level($audience_level['audience_level']);
					if ($db_audience_level)
					{
						$asset_audience_level['audience_levels_id'] = $db_audience_level->id;
					}
					else
					{
						$asset_audience_level['audience_levels_id'] = $this->assets_model->insert_audience_level($audience_level);
					}
					$asset_audience = $this->assets_model->insert_asset_audience($asset_audience_level);
				}
			}
		}
		// pbcoreAudienceLevel End
		
		// pbcoreAudienceRating Start
		if (isset($asset_children['pbcoreaudiencerating']))
		{
		
			foreach ($asset_children['pbcoreaudiencerating'] as $pbcore_aud_rating)
			{
				$audience_rating = array();
				$asset_audience_rating = array();
				$asset_audience_rating['assets_id'] = $asset_id;
				if (isset($pbcore_aud_rating['children']['audiencerating'][0]) && !empty($pbcore_aud_rating['children']['audiencerating'][0]['text']))
				{
					$audience_rating['audience_rating'] = $pbcore_aud_rating['children']['audiencerating'][0]['text'];
					$db_audience_rating = $this->assets_model->get_audience_rating($audience_rating['audience_rating']);
					if ($db_audience_rating)
					{
						$asset_audience_rating['audience_ratings_id'] = $db_audience_rating->id;
					}
					else
					{
						$asset_audience_rating['audience_ratings_id'] = $this->assets_model->insert_audience_rating($audience_rating);
					}
					$asset_audience_rate = $this->assets_model->insert_asset_audience_rating($asset_audience_rating);
				}
			}
		}
		// pbcoreAudienceRating End
		
		// pbcoreAnnotation Start
		if (isset($asset_children['pbcoreannotation']))
		{
		
			foreach ($asset_children['pbcoreannotation'] as $pbcore_annotation)
			{
				$annotation = array();
				$annotation['assets_id'] = $asset_id;
				if (isset($pbcore_annotation['children']['annotation'][0]) && !empty($pbcore_annotation['children']['annotation'][0]['text']))
				{
					$annotation['annotation'] = $pbcore_annotation['children']['annotation'][0]['text'];
					$asset_annotation = $this->assets_model->insert_annotation($annotation);
				}
			}
		}
		// pbcoreAnnotation End
		
		// pbcoreRelation Start here
		if (isset($asset_children['pbcorerelation']))
		{
		
			foreach ($asset_children['pbcorerelation'] as $pbcore_relation)
			{
				$assets_relation = array();
				$assets_relation['assets_id'] = $asset_id;
				$relation_types = array();
				if (isset($pbcore_relation['children']['relationtype'][0]['text']) && !empty($pbcore_relation['children']['relationtype'][0]['text']))
				{
					$relation_types['relation_type'] = $pbcore_relation['children']['relationtype'][0]['text'];
					$db_relations = $this->assets_model->get_relation_types($relation_types['relation_type']);
					if ($db_relations)
					{
						$assets_relation['relation_types_id'] = $db_relations->id;
					}
					else
					{
						$assets_relation['relation_types_id'] = $this->assets_model->insert_relation_types($relation_types);
					}
					if (isset($pbcore_relation['children']['relationidentifier'][0]))
					{
						$assets_relation['relation_identifier'] = $pbcore_relation['children']['relationidentifier'][0]['text'];
						$this->assets_model->insert_asset_relation($assets_relation);
					}
				}
			}
		}
		// pbcoreRelation End here
		// End By Nouman Tayyab
		
		// Start By Ali Raza
		// pbcoreCreator Start here
		if (isset($asset_children['pbcorecreator']))
		{
			foreach ($asset_children['pbcorecreator'] as $pbcore_creator)
			{
				$assets_creators_roles_d = array();
				$assets_creators_roles_d['assets_id'] = $asset_id;
				$creator_d = array();
				$creator_role = array();
				if (isset($pbcore_creator['children']['creator'][0]['text']) && !empty($pbcore_creator['children']['creator'][0]['text']))
				{
					$creator_d=$this->assets_model->get_creator_by_creator_name($pbcore_creator['children']['creator'][0]['text']);
					if($creator_d)
					{
						$assets_creators_roles_d['creators_id']=$creator_d->id;
					}
					else
					{
						// creator_affiliation , creator_source ,creator_ref
						$assets_creators_roles_d['creators_id']=$this->assets_model->insert_creators(array('creator_name'=>$pbcore_creator['children']['creator'][0]['text']));
					}
				}
				if (isset($pbcore_creator['children']['creatorrole'][0]) && !empty($pbcore_creator['children']['creatorrole'][0]['text']))
				{
					$creator_role=$this->assets_model->get_creator_role_by_role($pbcore_creator['children']['creatorrole'][0]['text']);
					if($creator_role)
					{
						$assets_creators_roles_d['creator_roles_id']=$creator_role->id;
					}
					else
					{
						// creator_role_ref , creator_role_source
						$assets_creators_roles_d['creator_roles_id']=$this->assets_model->insert_creator_roles(array('creator_role'=>$pbcore_creator['children']['creatorrole'][0]['text']));
					}
				}
				//print_r($assets_creators_roles_d);
				$assets_creators_roles_id=$this->assets_model->insert_assets_creators_roles($assets_creators_roles_d);
			}
		}
		// pbcoreCreator End here
		
		// pbcoreContributor Start here
		if (isset($asset_children['pbcorecontributor']))
		{
			foreach ($asset_children['pbcorecontributor'] as $pbcore_contributor)
			{
				$assets_contributors_d = array();
				$assets_contributors_d['assets_id'] = $asset_id;
				$contributor_d = array();
				$contributor_role = array();
				if (isset($pbcore_contributor['children']['contributor'][0]))
				{
					$contributor_d=$this->assets_model->get_contributor_by_contributor_name($pbcore_contributor['children']['contributor'][0]['text']);
					if($contributor_d)
					{
						$assets_contributors_d['contributors_id']=$contributor_d->id;
					}
					else
					{
						// contributor_affiliation ,	contributor_source, 	contributor_ref 
						$assets_contributors_d['contributors_id']=$this->assets_model->insert_contributors(array('contributor_name'=>$pbcore_contributor['children']['contributor'][0]['text']));
					}
				}
				if (isset($pbcore_contributor['children']['contributorrole'][0]) && !empty($pbcore_contributor['children']['contributorrole'][0]['text']))
				{
					$contributor_role=$this->assets_model->get_contributor_role_by_role($pbcore_contributor['children']['contributorrole'][0]['text']);
					if($contributor_role)
					{
						$assets_contributors_d['contributor_roles_id']=$contributor_role->id;
					}
					else
					{
						// contributor_role_source ,	contributor_role_ref 
						$assets_contributors_d['contributor_roles_id']=$this->assets_model->insert_contributor_roles(array('contributor_role'=>$pbcore_contributor['children']['contributorrole'][0]['text']));
					}
				}
				$assets_contributors_roles_id=$this->assets_model->insert_assets_contributors_roles($assets_contributors_d);
			}
		}
		// pbcorecontributor End here
		
		// pbcorePublisher Start here
		if (isset($asset_children['pbcorepublisher']))
		{
			foreach ($asset_children['pbcorepublisher'] as $pbcore_publisher)
			{
				$assets_publisher_d = array();
				$assets_publisher_d['assets_id'] = $asset_id;
				$publisher_d = array();
				$publisher_role = array();
				if (isset($pbcore_publisher['children']['publisher'][0]) && !empty($pbcore_publisher['children']['publisher'][0]['text']))
				{
					$publisher_d=$this->assets_model->get_publishers_by_publisher($pbcore_publisher['children']['publisher'][0]['text']);
					if($publisher_d)
					{
						$assets_publisher_d['publishers_id']=$publisher_d->id;
					}
					else
					{
						// publisher_affiliation ,	publisher_ref 
						$assets_publisher_d['publishers_id']=$this->assets_model->insert_publishers(array('publisher'=>$pbcore_publisher['children']['publisher'][0]['text']));
					}
					//Insert Data into asset_description
				}
				if (isset($pbcore_publisher['children']['publisherrole'][0]) && !empty($pbcore_publisher['children']['publisherrole'][0]['text']))
				{
					$publisher_role=$this->assets_model->get_publisher_role_by_role($pbcore_publisher['children']['publisherrole'][0]['text']);
					if($publisher_role)
					{
						$assets_publisher_d['publisher_roles_id']=$publisher_role->id;
					}
					else
					{
						// publisher_role_ref ,	publisher_role_source 
						$assets_publisher_d['publisher_roles_id']=$this->assets_model->insert_publisher_roles(array('publisher_role'=>$pbcore_publisher['children']['publisherrole'][0]['text']));
					}
				}
				//print_r($assets_publisher_d);
				$assets_publishers_roles_id=$this->assets_model->insert_assets_publishers_role($assets_publisher_d);
			}
		}
		// pbcorePublisher End here
		
		// pbcoreRightsSummary Start
		if (isset($asset_children['pbcorerightssummary']) && !empty($asset_children['pbcorerightssummary']))
		{
			foreach ($asset_children['pbcorerightssummary'] as $pbcore_rights_summary)
			{
				$rights_summary_d = array();
				$rights_summary_d['assets_id'] = $asset_id;
				if (isset($pbcore_rights_summary['children']['rightssummary'][0]) && !empty($pbcore_rights_summary['children']['rightssummary'][0]['text']))
				{
					$rights_summary_d['rights'] = $pbcore_rights_summary['children']['rightssummary'][0]['text'];
					//print_r($rights_summary_d);
					$rights_summary_ids[] = $this->assets_model->insert_rights_summaries($rights_summary_d);
				}
			}
		}
		// pbcoreRightsSummary End
		
		//pbcoreExtension Start
		if (isset($asset_children['pbcoreextension']) && !empty($asset_children['pbcoreextension']))
		{
			foreach ($asset_children['pbcoreextension'] as $pbcore_extension)
			{
				if (isset($pbcore_extension['children']['extensionauthorityused'][0]))
				{
					
					if(strtolower($pbcore_extension['children']['extensionauthorityused'][0]['text'])!=strtolower('AACIP Record Nomination Status'))
					{
						$extension_d = array();
						$extension_d['assets_id'] = $asset_id;
						$extension_d['extension_element'] = $pbcore_extension['children']['extensionauthorityused'][0]['text'];
						if (isset($pbcore_extension['children']['extension'][0]['text']))
						{
							$extension_d['extension_value'] = $pbcore_extension['children']['extension'][0]['text'];
						}
						
						$this->assets_model->insert_extensions($extension_d);
					}
					else
					{
						$nomination_d = array();
						$nomination_d['assets_id'] = $asset_id;
						if (isset($pbcore_extension['children']['extension'][0]['text']))
						{
							
							$nomunation_status=$this->assets_model->get_nomination_status_by_status($pbcore_extension['children']['extension'][0]['text']);
							if($nomunation_status)
							{
								$nomination_d['nomination_status_id'] =$nomunation_status->id;
							}
							else
							{
								$nomination_d['nomination_status_id']=$this->assets_model->insert_nomination_status(array("status"=>$pbcore_extension['children']['extension'][0]['text']));
							}
							$this->assets_model->insert_nominations($nomination_d);
						}						
					}
				}
			}
		}
		//pbcoreExtension End
		// End By Ali Raza
	}
	

}