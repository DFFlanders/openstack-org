<?php

/**
 * Copyright 2014 Openstack Foundation
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 **/
class SchedToolsPage extends Page
{
	static $db = array();
	static $has_one = array();
	static $has_many = array();

	static $defaults = array(
		'ShowInMenus' => false,
		'ShowInSearch' => false
	);


	function getCMSFields()
	{
		$fields = parent::getCMSFields();
		return $fields;
	}
}


class SchedToolsPage_Controller extends Page_Controller
{
	public static $allowed_actions = array(
		'ImportSpeakersFromSched' => 'ADMIN',
		'ListSpeakers' => 'ADMIN',
		'SpeakerTable' => 'ADMIN',
		'Presentations',
		'Upload',
		'Form',
		'Success',
		'LinkTo',
		'LinkToForm',
		'EmailSpeakers' => 'ADMIN'
	);

	function init()
	{

		parent::init();

		Requirements::CustomScript("

					jQuery(function(){

						$('#PresentationMediaUploadForm_Form_action_doUpload').hide();

						$('#PresentationMediaUploadForm_Form_UploadedMedia').change(function(){
						    path = $(this).val();
						    file = path.split('\\\\').pop();
						    $( '#file-well p:first' ).html( file );
						    $( '#file-well' ).removeClass( 'no-selected-file' );
						    $( '#file-well' ).addClass( 'selected-file' );
						    $('#PresentationMediaUploadForm_Form_action_doUpload').fadeIn();
						    $('.browseButton').html('Ready to upload.');
						    $('.browseButton').addClass('buttonFileSelected');
						});

						$('#PresentationMediaUploadForm_Form').submit(function(){
							$('#uploadProgressBarOuterBarG').css({ opacity: 1 });
						});

					});


			");

	}


	function ImportSpeakersFromSched()
	{

		$feed = new RestfulService('http://openstacksummitoctober2015tokyo.sched.org/api/role/export?api_key=36fde701e81eeea22e61e0b30942a804&role=speaker&format=xml&fields=username,name,email', 7200);

		$feedXML = $feed->request()->getBody();

		$feedXML = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $feedXML);

		$results = $feed->getValues($feedXML, 'speaker');

		// A new import overwrites previous data.
		// The table is trucated to remove previous entries and avoid duplicates.

		DB::Query("TRUNCATE SchedSpeaker");

		foreach ($results as $item) {

			$Speaker = new SchedSpeaker();

			$Speaker->username = $item->username;
			$Speaker->email = $item->email;
			$Speaker->name = $item->name;

			$Speaker->write();
		}

		echo "Speakers imported successfully.";


	}

	function ShowSchedSpeakers()
	{
		return SchedSpeaker::get();
	}

	function Presentations()
	{

		$Speaker = NULL;

		if (isset($_GET['key'])) {

			$key = Convert::raw2sql($_GET['key']);
			$username = SchedSpeaker::HashToUsername($key);
			$Speaker = SchedSpeaker::get()->filter('username', $username)->first();

		} elseif ($speakerID = Session::get('UploadMedia.SpeakerID')) {

			$Speaker = SchedSpeaker::get()->byID($speakerID);

		}

		// Speaker not found
		if (!$Speaker) return $this->httpError(404, 'Sorry, that does not appear to be a valid token.');

		Session::set('UploadMedia.SpeakerID', $Speaker->ID);

		$Presentations = $Speaker->PresentationsForThisSpeaker();

		// No presentations
		if (!$Presentations) return $this->httpError(404, 'Sorry, it does not appear that you have any presentations.');


		// IF there's only one presentation with no media, go ahead and forward to it's page
		if ($Presentations->count() == 1 && !$Presentations->first()->UploadedMedia()) {
			$PresentationID = $Presentations->first()->ID;
			$this->redirect($this->link() . 'Upload/' . $PresentationID);
			return;
		}

		$data["Speaker"] = $Speaker;
		$data["Presentations"] = $Presentations;

		return $this->Customise($data);


	}

	function Upload()
	{
		$PresentationID = $this->request->param("ID");

		if ( // make sure the data is numeric
			is_numeric($PresentationID) &&
			// make sure there's a presentation by that id
			($Presentation = VideoPresentation::get()->byID($PresentationID)) &&
			// pull the speaker from the session and make sure they are a speaker for this presentation
			($SpeakerID = Session::get('UploadMedia.SpeakerID')) &&
			($Presentation->IsASpeaker($SpeakerID))
		) {
			Session::set('UploadMedia.PresentationID', $Presentation->ID);

			$data['Presentation'] = $Presentation;
			return $this->Customise($data);

		} else {
			$data["HasError"] = TRUE;
			print_r($Presentation);
			echo "Is a speaker " . $Presentation->IsASpeaker($SpeakerID);
			return $this->Customise($data);
		}

	}

	function LinkTo()
	{
		$PresentationID = $this->request->param("ID");

		if ( // make sure the data is numeric
			is_numeric($PresentationID) &&
			// make sure there's a presentation by that id
			($Presentation = VideoPresentation::get()->byID($PresentationID)) &&
			// pull the speaker from the session and make sure they are a speaker for this presentation
			($SpeakerID = Session::get('UploadMedia.SpeakerID')) &&
			($Presentation->IsASpeaker($SpeakerID))
		) {
			Session::set('UploadMedia.PresentationID', $Presentation->ID);

			$data['Presentation'] = $Presentation;
			return $this->Customise($data);

		} else {
			$data["HasError"] = TRUE;
			return $this->Customise($data);
		}


	}

	function Form()
	{
		$Form = new PresentationMediaUploadForm($this, 'Form');
		return $Form;
	}

	function LinkToForm()
	{

		$PresentationID = Session::get('UploadMedia.PresentationID');
		$Presentation = VideoPresentation::get()->byID($PresentationID);

		$Form = new PresentationLinkToForm($this, 'LinkToForm');
		if ($Presentation) $Form->loadDataFrom($Presentation);
		return $Form;
	}

	function Success()
	{

		$data = NULL;

		if ((Session::get('UploadMedia.Success') == TRUE) &&
			($PresentationID = Session::get('UploadMedia.PresentationID')) &&
			($Presentation = VideoPresentation::get()->byID($PresentationID))
		) {
			$data["Presentation"] = $Presentation;
			$data["Filename"] = Session::get('UploadMedia.FileName');
			$data["PresentationURL"] = Session::get('UploadMedia.URL');

			if (Session::get('UploadMedia.Type') == 'File') {
				$data['IsFile'] = TRUE;
			} else {
				$data['IsURL'] = TRUE;
			}

			Session::clear('UploadMedia.Success');
			Session::clear('UploadMedia.FileName');
			Session::clear('UploadMedia.URL');
			Session::clear('UploadMedia.Type');


			return $this->Customise($data);
		} else {
			$this->redirect($this->link() . 'Presentations');
		}


	}


	public function ListSpeakers() {

		$filepath = $_SERVER['DOCUMENT_ROOT'].'/assets/shed-speaker-list.csv';
		$fp = fopen($filepath, 'w');

		// Setup file to be UTF8
		fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

		$Speakers = SchedSpeaker::get();

		foreach ($Speakers as $Speaker) {

			if($Speaker->PresentationsForThisSpeaker()->count()) {

				// Output speaker row
				$fields = array(
					$Speaker->name,
					$Speaker->email,
					$Speaker->SpeakerHash(),
					'https://www.openstack.org/speaker-upload/Presentations/?key=' . $Speaker->SpeakerHash()
				);

				fputcsv($fp, $fields);

			}
		}

		fclose($fp);

		header("Cache-control: private");
		header("Content-type: application/force-download");
		header("Content-transfer-encoding: binary\n");
		header("Content-disposition: attachment; filename=\"shed-speaker-list.csv\"");
		header("Content-Length: ".filesize($filepath));
		readfile($filepath);        

        
    }


	function EmailSpeakers()
	{

		$getVars = $this->request->getVars();


		$Speakers = SchedSpeaker::get();
		foreach ($Speakers as $Speaker) {

			if ($Speaker->PresentationsForThisSpeaker() &&
				!$Speaker->GeneralOrKeynote() &&
				!SchedSpeakerEmailLog::BeenEmailed($Speaker->email) &&
				$this->validEmail($Speaker->email)
			) {

				$To = $Speaker->email;
				$Subject = "Important Speaker Information for OpenStack Summit in Paris";

				$email = EmailFactory::getInstance()->buildEmail(SCHED_TOOLS_EMAIL_FROM, $To, $Subject);
				$email->setTemplate("UploadPresentationSlidesEmail");
				$email->populateTemplate($Speaker);


				if (isset($getVars['confirm'])) {
					SchedSpeakerEmailLog::addSpeaker($Speaker->email);
					$email->send();
				} else {
					echo $email->debug();
				}

				echo 'Email sent to ' . $Speaker->email . '<br/>';

			}


		}
	}


	/**
	 * Validate an email address.
	 * Provide email address (raw input)
	 * Returns true if the email address has the email
	 * address format and the domain exists.
	 */
	function validEmail($email)
	{
		$isValid = true;
		$atIndex = strrpos($email, "@");
		if (is_bool($atIndex) && !$atIndex) {
			$isValid = false;
		} else {
			$domain = substr($email, $atIndex + 1);
			$local = substr($email, 0, $atIndex);
			$localLen = strlen($local);
			$domainLen = strlen($domain);
			if ($localLen < 1 || $localLen > 64) {
				// local part length exceeded
				$isValid = false;
			} else if ($domainLen < 1 || $domainLen > 255) {
				// domain part length exceeded
				$isValid = false;
			} else if ($local[0] == '.' || $local[$localLen - 1] == '.') {
				// local part starts or ends with '.'
				$isValid = false;
			} else if (preg_match('/\\.\\./', $local)) {
				// local part has two consecutive dots
				$isValid = false;
			} else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
				// character not valid in domain part
				$isValid = false;
			} else if (preg_match('/\\.\\./', $domain)) {
				// domain part has two consecutive dots
				$isValid = false;
			} else if
			(!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
				str_replace("\\\\", "", $local))
			) {
				// character not valid in local part unless
				// local part is quoted
				if (!preg_match('/^"(\\\\"|[^"])+"$/',
					str_replace("\\\\", "", $local))
				) {
					$isValid = false;
				}
			}
			if ($isValid && !(checkdnsrr($domain, "MX") || checkdnsrr($domain, "A"))) {
				// domain not found in DNS
				$isValid = false;
			}
		}
		return $isValid;
	}

}
