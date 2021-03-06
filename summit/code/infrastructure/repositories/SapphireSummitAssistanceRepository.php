<?php

/**
 * Copyright 2015 OpenStack Foundation
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
final class SapphireSummitAssistanceRepository extends SapphireRepository implements ISummitAssistanceRepository
{

    public function __construct()
    {
        parent::__construct(new PresentationSpeakerSummitAssistanceConfirmationRequest);
    }

    /**
     * @param int $summit_id
     * @return array
     */
    public function getAssistanceBySummit($summit_id, $page, $page_size, $sort, $sort_dir)
    {

        $from = <<<SQL

FROM SummitEvent AS E
INNER JOIN Presentation ON Presentation.ID = E.ID
INNER JOIN Presentation_Speakers ON Presentation_Speakers.PresentationID = Presentation.ID
INNER JOIN PresentationSpeaker AS S ON S.ID = Presentation_Speakers.PresentationSpeakerID
INNER JOIN PresentationCategory  ON PresentationCategory.ID = Presentation.CategoryID
LEFT JOIN Member ON Member.ID = S.MemberID
LEFT JOIN SpeakerRegistrationRequest ON SpeakerRegistrationRequest.SpeakerID = S.ID
LEFT JOIN PresentationSpeakerSummitAssistanceConfirmationRequest AS ACR ON ACR.SpeakerID = S.ID AND ACR.SummitID = {$summit_id}
LEFT JOIN Affiliation AS A ON A.ID = (
	SELECT ID FROM Affiliation
    WHERE
    Affiliation.MemberID = Member.ID
    AND Affiliation.Current = 1
    ORDER BY EndDate DESC
    LIMIT 1
)
LEFT JOIN Org AS O ON O.ID = A.OrganizationID
WHERE
E.SummitID = {$summit_id}
AND E.Published = 1

SQL;


        $query = <<<SQL
SELECT
       S.ID AS speaker_id,
       Member.ID AS member_id,
       IFNULL(CONCAT(Member.FirstName ,' ',Member.Surname), CONCAT(S.FirstName ,' ',S.LastName)) AS name,
       IFNULL(Member.Email, SpeakerRegistrationRequest.Email) AS email,
       O.Name AS company,
       E.Title AS presentation,
       PresentationCategory.Title AS track,
       ACR.OnSitePhoneNumber AS phone,
       ACR.IsConfirmed AS confirmed,
       ACR.RegisteredForSummit AS registered,
       ACR.CheckedIn AS checked_in
{$from}
ORDER BY {$sort} {$sort_dir}
SQL;


        $query_count = <<<SQL
SELECT COUNT(*)
{$from}
SQL;

        if ($page && $page_size) {
            $offset = ($page - 1 ) * $page_size;
            $query .=
                <<<SQL
 LIMIT {$offset}, {$page_size};
SQL;

        }
        $total  = DB::query($query_count)->value();
        $data   = DB::query($query);
        $result = array('Total' => $total, 'Data' => $data);

        return $result;
    }

    public function getPresentationsAndSpeakersBySummit($summit_id, $page, $page_size, $sort, $sort_dir, $search_term)
    {

$query_body = <<<SQL

FROM SummitEvent AS E
INNER JOIN Presentation ON Presentation.ID = E.ID
INNER JOIN Presentation_Speakers ON Presentation_Speakers.PresentationID = Presentation.ID
INNER JOIN PresentationSpeaker AS S ON S.ID = Presentation_Speakers.PresentationSpeakerID
INNER JOIN PresentationCategory  ON PresentationCategory.ID = Presentation.CategoryID
LEFT JOIN Member ON Member.ID = S.MemberID
LEFT JOIN SpeakerRegistrationRequest ON SpeakerRegistrationRequest.SpeakerID = S.ID
LEFT JOIN PresentationSpeakerSummitAssistanceConfirmationRequest AS ACR ON ACR.SpeakerID = S.ID AND ACR.SummitID = {$summit_id}
LEFT JOIN SummitAbstractLocation AS L ON L.ID = E.LocationID
LEFT JOIN (
    SELECT Type, Code, SpeakerID FROM SpeakerSummitRegistrationPromoCode
    INNER JOIN SummitRegistrationPromoCode ON SummitRegistrationPromoCode.ID = SpeakerSummitRegistrationPromoCode.ID
    AND SummitRegistrationPromoCode.SummitID = $summit_id
) AS PromoCodes ON PromoCodes.SpeakerID = S.ID
WHERE
E.SummitID = {$summit_id}
AND E.Published = 1

SQL;


        if ($search_term) {
            $query_body .= " AND (E.Title LIKE '%{$search_term}%' OR S.FirstName LIKE '%{$search_term}%'
                            OR S.LastName LIKE '%{$search_term}%' OR CONCAT(S.FirstName,' ',S.LastName) LIKE '%{$search_term}%')";
        }

        $query_count = "SELECT COUNT(*) ";

        $query = <<<SQL
        SELECT
        E.Title AS presentation,
        E.Published AS published,
        PresentationCategory.Title AS track,
        E.StartDate AS start_date,
        L.Name AS location,
        S.ID AS speaker_id,
        Member.ID AS member_id,
       IFNULL(CONCAT(Member.FirstName ,' ',Member.Surname), CONCAT(S.FirstName ,' ',S.LastName)) AS name,
        IFNULL(Member.Email, SpeakerRegistrationRequest.Email) AS email,
        PromoCodes.Type AS code_type,
        PromoCodes.Code AS promo_code,
        ACR.IsConfirmed AS confirmed,
        ACR.RegisteredForSummit AS registered,
        ACR.CheckedIn AS checked_in,
        ACR.OnSitePhoneNumber AS phone,
        E.ID AS presentation_id,
        ACR.ID AS assistance_id
SQL;

        $query .= $query_body." ORDER BY {$sort} {$sort_dir}";
        $query_count .= $query_body;

        if ($page && $page_size) {
            $offset = ($page - 1 ) * $page_size;
            $query .= " LIMIT {$offset}, {$page_size}";
        }

        $data = DB::query($query);
        $total = DB::query($query_count)->value();
        $result = array('Total' => $total, 'Data' => $data);
        return $result;
    }

    public function getRoomsBySummitAndDay($summit_id, $date, $event_type='all', $venue='all')
    {

        $query = <<<SQL
SELECT E.ID AS id ,E.StartDate AS start_date, E.EndDate AS end_date, 'K' AS code, E.Title AS event,
L.Name AS room, R.Capacity AS capacity,COUNT(DISTINCT(SA.SpeakerID),SA.SpeakerID IS NOT NULL) AS speakers, E.HeadCount AS headcount, COUNT(A.ID) AS total
FROM SummitEvent AS E
LEFT JOIN Presentation AS P ON P.ID = E.ID
LEFT JOIN Presentation_Speakers AS PS ON PS.PresentationID = P.ID
LEFT JOIN PresentationSpeakerSummitAssistanceConfirmationRequest AS SA ON PS.PresentationSpeakerID = SA.SpeakerID AND SA.SummitID = {$summit_id}
LEFT JOIN SummitAbstractLocation AS L ON L.ID = E.LocationID
LEFT JOIN SummitVenueRoom AS R ON R.ID = L.ID
LEFT JOIN SummitAttendee_Schedule AS A ON A.SummitEventID = E.ID
WHERE DATE(E.StartDate) = '{$date}' AND E.SummitID = {$summit_id}
SQL;
        if ($event_type == 'presentation') {
            $query .= <<<SQL
 AND E.ClassName = 'Presentation'
SQL;
        }

        if ($venue != 'all') {
            $query .= <<<SQL
 AND E.LocationID = {$venue}
SQL;
        }

        $query .= <<<SQL
 GROUP BY E.ID
SQL;

        return DB::query($query);
    }

    public function getAttendeesWithCalendar($summit_id)
    {
        $query = <<<SQL
SELECT COUNT(DISTINCT(ASched.SummitAttendeeID)) AS calcount
FROM SummitAttendee_Schedule AS ASched
LEFT JOIN SummitEvent AS E ON E.Id = ASched.SummitEventID
WHERE E.SummitID = {$summit_id}
SQL;

        return DB::query($query);
    }
}