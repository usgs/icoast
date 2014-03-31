<?php

$pageName = "help";
$cssLinkArray = array();
$embeddedCSS = '';
$javaScriptLinkArray = array();
$javaScript = '';

require 'includes/globalFunctions.php';
require $dbmsConnectionPath;
$userData = FALSE;

if (isset($_COOKIE['userId']) && isset($_COOKIE['authCheckCode'])) {

    $userId = $_COOKIE['userId'];
    $authCheckCode = $_COOKIE['authCheckCode'];

    $userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode, FALSE);
    if ($userdata) {
        $authCheckCode = generate_cookie_credentials($DBH, $userId);
    }
}

$jQueryDocumentDotReadyCode = <<<EOL

   var revealedFaqs = 0;
   var totalFaqs = 0;

   $('.faqQuestion').each(function() {
       totalFaqs ++;
       $(this).click(function() {
           if ($(this).find('p:first-of-type').html() == '+') {
                $(this).find('p:first-of-type').html('-');
                revealedFaqs = revealedFaqs + 1;
           } else {
                $(this).find('p:first-of-type').html('+');
                revealedFaqs = revealedFaqs - 1;
           }
           $(this).next().slideToggle();

           if (revealedFaqs > 0) {
                $('#hideAllFaqs').removeClass('disabledClickableButton');
           } else {
                $('#hideAllFaqs').addClass('disabledClickableButton');
                revealedFaq = 0;
           }

           if (revealedFaqs < totalFaqs) {
                $('#revealAllFaqs').removeClass('disabledClickableButton');
           } else {
                $('#revealAllFaqs').addClass('disabledClickableButton');
                revealedFaqs = totalFaqs;
           }

            console.log(totalFaqs);
            console.log(revealedFaqs);
       });
   });

   $('#revealAllFaqs').click(function() {
       $('.faq').each(function () {
            $(this).find('.faqQuestion p:first-of-type').text('-');
            $(this).find('.faqAnswer').slideDown();
            $('#revealAllFaqs').addClass('disabledClickableButton');
            $('#hideAllFaqs').removeClass('disabledClickableButton');
            revealedFaqs = totalFaqs;
       });
        console.log(totalFaqs);
        console.log(revealedFaqs);
   });

   $('#hideAllFaqs').click(function() {
       $('.faq').each(function () {
            $(this).find('.faqQuestion p:first-of-type').text('+');
            $(this).find('.faqAnswer').slideUp();
            $('#hideAllFaqs').addClass('disabledClickableButton');
            $('#revealAllFaqs').removeClass('disabledClickableButton');
            revealedFaqs = 0;
       });
        console.log(totalFaqs);
        console.log(revealedFaqs);
   });




EOL;

