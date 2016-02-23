<?php

/**
 * Adds functionality to Member to support presentations and summit features
 *
 * @author  Uncle Cheese <unclecheese@leftandmain.com>
 */
class PresentationMemberExtension extends DataExtension
{

    private static $db = array
    (
        'PresentationList' => 'Text',        
    );

    private static $has_many = array
    (
        'Presentations'          => 'Presentation',
        'SummitStates'           => 'SpeakerSummitState'
    );

    private static $has_one = array (
    	'PresentationPriority' => 'PresentationPriority'
    );

    /**
     * Gets presentations, ordered in a persistent random fashion
     *         
     * @return DataList
     */
    public function getRandomisedPresentations($category_id = null, $summit = null) {
        $mid = Member::currentUserID();
        if(!$summit) $summit = Summit::get_active();
        $priority = $summit->PresentationPriorities()->filter('Members.ID', $mid);

        if(!$priority->exists()) {
        	$priority = PresentationPriority::get()->filter(
        		'SummitID', 
        		$summit->ID
        	)
        	->sort('RAND()')
        	->first();        	
        	if(!$priority) {
        		return false;        	
        	}
        	$this->owner->PresentationPriorityID = $priority->ID;
        	$this->owner->write();
        }
        
        $list = $priority->getPriorityList();
        if(!count($list)) return false;
        
        $presentations =  Presentation::get()
                ->innerJoin('PresentationCategory', 'PresentationCategory.ID = Presentation.CategoryID')
                ->where("SummitEvent.Title IS NOT NULL")
                ->where("SummitEvent.Title <> '' ")
                ->where("PresentationCategory.VotingVisible = 1 ")                
                ->filter('Presentation.Status', 'Received')
                ->filter('SummitID', $summit->ID)
                ->sort("FIND_IN_SET(Presentation.ID, '".implode(',', $list)."')");

        if(!empty($category_id) && intval($category_id) > 0) {
            $presentations = $presentations->filter('CategoryID', $category_id);
        }

        return $presentations;

    }


    /**
     * Gets presentations that this user has voted on
     * @return DataList 
     */
    public function getVotedPresentations() {
        return PresentationVote::get()->filter('MemberID',Member::currentUserID());
    }


    /**
     * Removes a presentation from the user's random list
     * @param  int $id The presentation ID     
     */
    public function removePresentation($id) {
        if(!$this->owner->PresentationList) return;

        $ids = Convert::json2array($this->owner->PresentationList);
        unset($ids[$id]);

        $this->owner->PresentationList = Convert::array2json($ids);
        $this->owner->write();
    }


    /**
     * Returns true if the user is a speaker in a given presentation
     * @param boolean
     */
    public function IsSpeaker($presentation = null) {
        if($presentation === null) {
            return Presentation::get()
                    ->relation('Speakers')
                    ->find('MemberID', $this->owner->ID);
        }

        if(is_numeric($presentation)) {
            $p = Presentation::get()->byID($presentation);
        }
        else if($presentation instanceof Presentation) {
            $p = $presentation;
        }

        if($p) {
            return $p->Speakers()->find('MemberID', $this->owner->ID);
        }
    }


    /**
     * Gets arbitrary state for a given summit, such as "bureau" for having
     * been asked about the bureau
     *     
     * @param  string $event  The abitrary event name
     * @param  Summit $summit
     * @return SpeakerSummitState
     */
    public function getSummitState($event, $summit = null) {
        $summit = $summit ?: Summit::get_active();
        if(!$summit) {
            return false;
        }

        return $this->owner->SummitStates()->filter(array(
            'Event' => $event,
            'SummitID' => $summit->ID
        ))->first();
    }


    /**
     * Sets arbitrary state for a given summit, such as "bureau" for having
     * been asked about the bureau
     *     
     * @param  string $event  The abitrary event name
     * @param  string $notes
     * @param  Summit $summit
     * @return SpeakerSummitState
     */
    public function setSummitState($event, $summit = null, $notes = null) {
        $summit = $summit ?: Summit::get_active();
        if(!$summit) {
            return false;
        }

        $this->unsetSummitState($event, $summit);

        $state = SpeakerSummitState::create(array(
            'Event' => $event,
            'Notes' => $notes,
            'SummitID' => $summit->ID,
            'MemberID'=> $this->owner->ID
        ));

        $state->write();
    }


    /**
     * Removes arbitrary state for a given summit
     * @param  string $event 
     * @param  Summit $summit      
     */
    public function unsetSummitState($event, $summit = null) {
        $state = $this->getSummitState($event, $summit);
        if($state) $state->delete();        
    }

    /**
     * @return PresentationSpeaker|null
     */
    public function getSpeakerProfile() {
        return PresentationSpeaker::get()->filter(array(
            'MemberID' => $this->owner->ID,
        ))->first();
    }

    /**
     * Sends a welcome email to the user     
     */
    public function sendWelcomeEmail() {
        Email::create() 
            ->setTo($this->owner->Email)
            ->setUserTemplate('member-welcome')
            ->populateTemplate(array('Member' => $this->owner))
            ->send();
    }


}