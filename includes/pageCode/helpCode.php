<?php

require_once('includes/globalFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

$pageCodeModifiedTime = filemtime(__FILE__);
$userData = authenticate_user($DBH, FALSE);

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
           $(this).next().slideToggle(positionFeedbackDiv);

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
       });
   });

   $('#revealAllFaqs').click(function() {
        $('#usgsfooter').css({
            visibility: 'hidden'
        });
       $('.faq').each(function () {
            $(this).find('.faqQuestion p:first-of-type').text('-');
            $(this).find('.faqAnswer').slideDown();
            $('#revealAllFaqs').addClass('disabledClickableButton');
            $('#hideAllFaqs').removeClass('disabledClickableButton');
            revealedFaqs = totalFaqs;
       });
        setTimeout(
            function()
            {
              moveFooter();
            $('#usgsfooter').css({
                visibility: 'visible'
            });
            }, 500);
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
        setTimeout(
            function()
            {
              moveFooter();
            }, 500);
        console.log(totalFaqs);
        console.log(revealedFaqs);
   });




EOL;

