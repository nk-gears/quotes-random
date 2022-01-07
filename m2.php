	public function test($campaign_id)
	{
		
		$campaign = $this->SponsorCampaign->find('first', [
			'recursive' => -1,
			'conditions' => ['id = ' . $campaign_id]
		]);
		$campaign = $campaign['SponsorCampaign'];
		
		$response = null;
		$requestString = null;
		
		if ($this->request->is('post')) {
			
			$lead_fields = $this->request->data['SponsorCampaign'];
			
			$rules = json_decode($campaign['rules'], true);
			
			$valid_campaign = true;
			
			foreach ($rules as $name => $value) {
				
				if (empty($value)) {
					continue;
				}
				
				switch ($name) {
					case 'age_min':
						
						if (isset($lead_fields['birthdate']) && !empty($lead_fields['birthdate'])) {
							
							$age = -1;
							
							try {
								
								$birthdate = str_replace('/', '-', $lead_fields['birthdate']);
								$birthdate = date('Y-m-d', strtotime($birthdate));
								
								$dob = date_create($birthdate);
								$now = date_create('today');
								$age = date_diff($dob, $now)->y;
								
							} catch (Exception $e) {
							}
							
							if ($value > $age) {
								$valid_campaign = false;
								$response['error'] = 'Date of birth is invalid';
								break 2;
							}
							
						} else {
							$valid_campaign = false;
							$response['error'] = 'Date of birth is invalid';
							break 2;
						}
						
						break;
					case 'age_max':
						
						if (isset($lead_fields['birthdate']) && !empty($lead_fields['birthdate'])) {
							
							$age = -1;
							
							try {
								
								$birthdate = str_replace('/', '-', $lead_fields['birthdate']);
								$birthdate = date('Y-m-d', strtotime($birthdate));
								
								$dob = date_create($birthdate);
								$now = date_create('today');
								$age = date_diff($dob, $now)->y;
								
							} catch (Exception $e) {
							}
							
							if ($value < $age) {
								$valid_campaign = false;
								$response['error'] = 'Date of birth is invalid';
								break 2;
							}
							
						} else {
							$valid_campaign = false;
							$response['error'] = 'Date of birth is invalid';
							break 2;
						}
						
						break;
					case 'gender':
						
						if (isset($lead_fields['gender']) && !empty($lead_fields['gender'])) {
							
							$gender = strtolower($lead_fields['gender']);
							
							if ($gender == 'm') {
								$gender = 'male';
							} elseif ($gender == 'f') {
								$gender = 'female';
							}
							
							if (strtolower($value) != $gender) {
								$valid_campaign = false;
								$response['error'] = 'Gender is invalid';
								break 2;
							}
							
						} else {
							$valid_campaign = false;
							$response['error'] = 'Gender is invalid';
							break 2;
						}
						
						break;
					case 'postcodes':
						
						if (isset($lead_fields['postcode']) && !empty($lead_fields['postcode'])) {
							
							$postcode = $lead_fields['postcode'];
							$postcodes = explode(',', $value);
							
							if (!in_array($postcode, $postcodes)) {
								$valid_campaign = false;
								$response['error'] = 'POstcode out of range';
								break 2;
							}
							
						} else {
							$valid_campaign = false;
							$response['error'] = 'Postcode out of range';
							break 2;
						}
						
						break;
					case 'postcodes_blacklist_csv':
						
						if (isset($lead_fields['postcode']) && !empty($lead_fields['postcode'])) {
							
							$csv = array_map('str_getcsv', file($value));
							$csv = array_column($csv, 0);
							
							if (in_array($lead_fields['postcode'], $csv)) {
								$valid_campaign = false;
								$response['error'] = 'Postcode out of range';
								break 2;
							}
							
						} else {
							$valid_campaign = false;
							$response['error'] = 'Postcode out of range';
							break 2;
						}
						
						break;
					case 'states':
						
						if (isset($lead_fields['postcode']) && !empty($lead_fields['postcode'])) {
							
							$postcode = $lead_fields['postcode'];
							
							$state = strtolower($this->SponsorCampaign->postcodeToState($postcode));
							
							if (!in_array($state, $value)) {
								$valid_campaign = false;
								$response['error'] = 'State out of range';
								break 2;
							}
							
						} else {
							$valid_campaign = false;
							$response['error'] = 'State out of range';
							break 2;
						}
						
						break;
					case 'phone_suppression':
						
						if ($value === '0 numbers suppressed') {
							break;
						}
						
						if (isset($lead_fields['phone']) && !empty($lead_fields['phone'])) {
							
							$phone = $lead_fields['phone'];
							$phone2 = ltrim($lead_fields['phone'], '0');
							$phone3 = 0 . (int)$lead_fields['phone'];
							
							App::uses('Suppression', 'Model');
							$suppression = new Suppression();
							
							$is_suppressed = $suppression->hasAny([
								'campaign_id' => $campaign['id'],
								'phone'       => [$phone, $phone2, $phone3]
							]);
							
							if ($is_suppressed) {
								$valid_campaign = false;
								$response['error'] = 'Phone is suppressed';
								break 2;
							}
							
						} else {
							$valid_campaign = false;
							$response['error'] = 'Phone is suppressed';
							break 2;
						}
						
						break;
				}
				
			}
			
			if ($valid_campaign) {
				
				$fields = json_decode($campaign['fields'], true);
				$custom_fields = json_decode($campaign['custom_fields']);
				
				$lead = [
					'id' => 0,
					'lead_fields' => $lead_fields
				];
				
				$matched_fields = $this->SponsorCampaign->getMatchedFields($fields, $custom_fields, $lead, 0);
				if ($matched_fields === false) {
					$response['error'] = 'An error occurred while generating field object';
				} else {
					
					switch ($campaign['notification_type']) {
						case '1':
							
							$client = $this->Client->find('first', [
								'conditions' => [
									'id' => $campaign['client_id']
								],
								'fields'     => ['id', 'first_name'],
								'recursive'  => -1
							]);
							
							$res = $this->CampaignSub->sendEmail($matched_fields, $campaign['notification_endpoint'], $campaign['name'], $client['Client']['first_name']);
							
							break;
						case '2':
							$res = $this->CampaignSub->sendCsv($matched_fields, $campaign['csv_file_name']);
							break;
						case '3':
							$res = $this->CampaignSub->sendPost($matched_fields, $campaign['notification_endpoint']);
							break;
						case '4':
							$res = $this->CampaignSub->sendGet($matched_fields, $campaign['notification_endpoint']);
							break;
						case '5':
							$res = $this->CampaignSub->sendJson($matched_fields, $campaign['notification_endpoint']);
							break;
						case 'pardot':
							
							$pardot_email = $campaign['pardot_email'];
							$pardot_password = $campaign['pardot_password'];
							$pardot_user_key = $campaign['pardot_user_key'];
							
							$res = $this->CampaignSub->sendPardot($matched_fields, $campaign['notification_endpoint'], $campaign['id'], $pardot_email, $pardot_password, $pardot_user_key);
							
							break;
						default:
							$res = false;
					}
					
					$response = $res['body'];
					$requestString = $res['request_string'];
				}
			}
		}
		
		$this->set(['campaign' => $campaign, 'response' => $response, 'requestString' => $requestString]);
	}