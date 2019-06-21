<?php
ob_start();
$pageModifiedTime = filemtime(__FILE__);

require_once("includes/pageCode/loginCode.php");

$pageBody = <<<EOL
    <div id="contentWrapper">
        <h1>Data Collection and Privacy Statements</h1>
        <div id="govStatementWrapper">
          <h2>Paperwork Reduction Act Statement</h2>
          <p>This information collection is authorized by The National Climate Program Act of 1978 
            and The Coastal Zone Management Act of 1976. Your response is voluntarily. We estimate 
            it will take approximately 2.5 minutes per classification of a matched pair of 
            photographs to submit a response. We ask you for some basic organizational and contact 
            information to help us interpret the results.
          </p>
          <p>In accordance with the Paperwork Reduction Act (44 USC 3501), an agency may not conduct 
            or sponsor and a person is not required to respond to a collection of information unless 
            it displays a currently valid Office of Management and Budget control number. OMB has 
            reviewed and approved this information collection and assigned OMB Control Number 
            1028-0109. You may submit comments on any aspect of this information collection, including 
            the accuracy of the estimated burden hours and suggestions to reduce this burden. Send 
            your comments to: Information Collections Clearance Officer, US Geological Survey, 
            <a href="mailto:gs-info_collections@usgs.gov">gs-info_collections@usgs.gov</a>.
          </p>
            
          <h2>Privacy Act Statement</h2>
          <p><span class="paragraphPrefix">Authority:</span> The National Climate Program Act of 
            1978 and the The Coastal Zone Management Act of 1976.
          </p>
          <p><span class="paragraphPrefix">System of Records:</span> DOI Social Networks 
            (Interior/USGS-8) published at 76 FR 44033, 7/22/2011].
          </p>
          <p><span class="paragraphPrefix">Principal purpose:</span> The U.S. Geological Survey 
            (USGS) conducts investigations of coastal hazards associated with major hurricane 
            landfall. These efforts document the nature, magnitude, and variability of coastal 
            changes such as beach erosion, overwash deposition, island breaching, and destruction 
            of infrastructure. The assessments and observations provide information needed to 
            understand, prepare for, and respond to coastal disasters. In support of this research, 
            the USGS has been taking oblique aerial photographs of the coast before and after major 
            storms since 1996 and has amassed a database of over 190,000 photographs of the Gulf 
            and Atlantic Coasts. Computers cannot yet automatically analyze these data. Human 
            intelligence is needed, and USGS does not have the personnel or the capacity for this. 
            Volunteer Citizen Scientists serve as our “eyes on the coast” to assist with the 
            analysis of this photographic data base. The iCoast—Did the Coast Change? website 
            posts a suite of pre- and post-storm photographs from a major storm, allowing citizen 
            scientists to compare photographs and classify the changes they see with predefined 
            tags.
          </p>
          
          <p><span class="paragraphPrefix">Routine use:</span> Citizen scientists identify coastal 
            landforms, determine the storm impacts to coastal infrastructure and landforms, and 
            indicate other changes, including response and recovery efforts. These collected data 
            will be used by USGS scientists to ground truth and fine-tune their models of coastal 
            change. These mathematical models predict the likely interaction between coastal features 
            such as beaches and dunes and storm surge. A body of citizen observations will allow for 
            more accurate predictions of vulnerability. These model predictions are typically shared 
            with Federal, State, and local authorities both before and after storms.
          </p>
          
          <p><span class="paragraphPrefix">Disclosure is voluntary:</span> All information, except 
            email address (used as a user account identifier), is voluntary. Individuals have the 
            option to not provide any additional information.
          </p>
          <p><span class="paragraphPrefix">Data Sharing:</span> Contact information will NOT be shared 
            with third parties and users have the option both at the time of registration and 
            subsequently in the "Profile" page to opt out of all iCoast related communications.
          </p>
        </div>
        <div id="statementAgreementButtons">
          <input type="button" id="cancelButton" class="clickableButton" value="Cancel Login/Registration">
          <input type="button" id= "continueButton" class="clickableButton" value="Continue Login/Registration">
        </div>
    </div>
EOL;

require_once("includes/template.php");



