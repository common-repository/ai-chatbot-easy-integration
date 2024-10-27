jQuery(document).ready(function ($) {


    // show settings section
jQuery("body").on("click", ".ai-chatbot-easy-integration-settings-section", function (e) {
    e.preventDefault();
    var selectedid = '.'+jQuery(this).attr('data-id');
    if(jQuery(selectedid).hasClass('hidden')){
    jQuery(selectedid).show();
    jQuery(selectedid).removeClass('hidden');
    jQuery(this).attr('aria-expanded','true');
    }else{
        jQuery(selectedid).hide();
        jQuery(selectedid).addClass('hidden');
        jQuery(this).attr('aria-expanded','false');
    }

});


// show chat transcript
jQuery("body").on("click", ".ai-chatbot-easy-integration-showlog", function (e) {
    e.preventDefault();

    jQuery( ".ai-chatbot-easy-integration-shownotes" ).each(function( index ) {
        if(jQuery(this).attr('aria-expanded') === 'true'){
            jQuery(this).trigger('click');
        }
      });

    var selectedid = '#'+jQuery(this).attr('data-id');
    if(jQuery(selectedid).hasClass('hidden')){
    jQuery(selectedid).show();
    jQuery(selectedid).removeClass('hidden');
    jQuery(this).attr('aria-expanded','true');
    }else{
        jQuery(selectedid).hide();
        jQuery(selectedid).addClass('hidden');
        jQuery(this).attr('aria-expanded','false');
    }


});


// show chat notes
jQuery("body").on("click", ".ai-chatbot-easy-integration-shownotes", function (e) {
    e.preventDefault();

    jQuery( ".ai-chatbot-easy-integration-showlog" ).each(function( index ) {
        if(jQuery(this).attr('aria-expanded') === 'true'){
            jQuery(this).trigger('click');
        }
      });

    var selectedid = '#'+jQuery(this).attr('data-id');
    if(jQuery(selectedid).hasClass('hidden')){
    jQuery(selectedid).show();
    jQuery(selectedid).removeClass('hidden');
    jQuery(this).attr('aria-expanded','true');
    }else{
        jQuery(selectedid).hide();
        jQuery(selectedid).addClass('hidden');
        jQuery(this).attr('aria-expanded','false');
    }



});



// show chat window
jQuery("body").on("click", ".ai_chatbot_easy_integration-showchat", function (e) {
    e.preventDefault();
    ai_chatbot_easy_integration_open_close_chat_dialog();
});
jQuery("body").on("click", ".ai-chatbot-easy-integration-chatwindow-close", function (e) {
    e.preventDefault();
    ai_chatbot_easy_integration_open_close_chat_dialog();
});

// open close chat dialog
function ai_chatbot_easy_integration_open_close_chat_dialog(){
    if(jQuery('.ai-chatbot-easy-integration-chatwindow').length > 0){
        if(jQuery('.ai_chatbot_easy_integration-showchat').attr('aria-expanded') == 'true'){
            
        jQuery('.ai-chatbot-easy-integration-chatwindow').hide();
        jQuery('.ai-chatbot-easy-integration-chatwindow-overlay').hide();
        jQuery('.ai_chatbot_easy_integration-showchat').attr('aria-expanded', 'false');
        jQuery('.ai_chatbot_easy_integration-showchat').focus();
    
        }else{
            jQuery('.ai-chatbot-easy-integration-chatwindow').show();
            jQuery('.ai-chatbot-easy-integration-chatwindow-overlay').show();
            ai_chatbot_easy_integration_trapFocus(document.getElementById('ai-chatbot-easy-integration-chatwindow'));
            jQuery('.ai_chatbot_easy_integration-showchat').attr('aria-expanded', 'true');
            jQuery('.ai-chatbot-easy-integration-chatwindow-close').focus();
        }
        } else{
            if(jQuery('.ai_chatbot_easy_integration-showchat').attr('aria-expanded') == 'true'){
                jQuery('.ai-chatbot-easy-integration-chatwindowmarketing').hide();
                jQuery('.ai-chatbot-easy-integration-chatwindow').attr('aria-expanded', 'false');
                jQuery('.ai_chatbot_easy_integration-showchat').focus();
                }else{
                    jQuery('.ai-chatbot-easy-integration-chatwindowmarketing').show();
                    jQuery('.ai_chatbot_easy_integration-showchat').attr('aria-expanded', 'true'); 
                }
        }
}

// trap focus
function ai_chatbot_easy_integration_trapFocus(element) {
    setTimeout(function() { 
        var focusableEls = element.querySelectorAll('a[href]:not([disabled]), button:not([disabled]), textarea:not([disabled]), input[type="text"]:not([disabled]), input[type="radio"]:not([disabled]), input[type="checkbox"]:not([disabled]), select:not([disabled])');
        var lastFocusableEl = focusableEls[focusableEls.length-1];
        var firstFocusableEl = focusableEls[0];
        KEYCODE_TAB = 9;
        
        //console.log(lastFocusableEl);
        element.addEventListener('keydown', function (e) {
        var isTabPressed = (e.key === 'Tab' || e.keyCode === KEYCODE_TAB);
        
        if (!isTabPressed)
        return;
        
        if (e.shiftKey) /* shift + tab */ {
        
        if (document.activeElement === firstFocusableEl) {
        lastFocusableEl.focus();
        e.preventDefault();
        }
        } else /* tab */ {
        if (document.activeElement === lastFocusableEl) {
        firstFocusableEl.focus();
        e.preventDefault();
        }
        }
        
        });
        }, 900);
}


 // update assigned chat window   

 jQuery("body").on("click", "#ai-chatbot-easy-integration-keyword-search", function (e) {
    e.preventDefault();
    var seperator='&';
    var resturl = ai_chatbot_easy_integration_variables['resturl'];
    var keyword = jQuery("#ai-chatbot-easy-integration-keyword").val();
  
    if(resturl.search('/wp-json/')>0) seperator='?';
    
    $.ajax({
    url: ai_chatbot_easy_integration_variables['resturl']+'ai_chatbot_easy_integration/v1/keywordsearch/'+seperator+'_wpnonce='+ai_chatbot_easy_integration_variables['nonce']+'&keyword='+keyword,
    async: true, 
    dataType: "html",
    error: function(e){ console.log('failed keyword search ');},
    success: 
    function(data){
         jQuery('#ai_chatbot_easy_integration_log').html(data);
         jQuery('.ai-chatbot-easy-integration-full-chatlog').show();
    
    }
    });
});	

/**
 * Open ai chat scripts
 */
jQuery("body").on("click", ".ai_chatbot_easy_integration_open_button, .ai_chatbot_easy_integration_cancel", function (e) {
    e.preventDefault();
    if(jQuery('.ai_chatbot_easy_integration_form_popup').is(':visible')){
        jQuery('.ai_chatbot_easy_integration_form_popup').hide();
        jQuery('.ai_chatbot_easy_integration_open_button').focus();
    }
    else{
        jQuery('.ai_chatbot_easy_integration_form_popup').show();
        jQuery('#ai_chatbot_easy_integration_msg').focus();
        ai_chatbot_easy_integration_trapFocus(document.getElementById('ai_chatbot_easy_integration_chatdialog'));
    }
});

// hide instructions
jQuery("body").on("click", ".ai_chatbot_easy_integration_close_instructions", function (e) {
    e.preventDefault();
    jQuery('.ai-chatbot-easy-integration-instructions').hide();
       
});


 // send chat message  
 jQuery("body").on("click", ".ai_chatbot_easy_integration_send", function (e) {
    e.preventDefault();
    var seperator='&';
    var resturl = ai_chatbot_easy_integration_variables['resturl'];
    var message = jQuery("#ai_chatbot_easy_integration_msg").val();
    var faq = jQuery(this).attr('data-faq');
    var sessionid = jQuery("#ai_chatbot_easy_integration_sessionid").val();
    var agent = jQuery(this).attr('data-agent');
    var openai_action = jQuery("#ai_chatbot_easy_integration_openai_action").val();


    jQuery("#ai_chatbot_easy_integration_msg").val('');

    jQuery("#ai_chatbot_easy_integration_chat_status_message").html(ai_chatbot_easy_integration_variables['message_sent']+' '+message);

    if('false' === faq){
    jQuery("#ai_chatbot_easy_integration_chat_dialog_container").append('<p><strong>'+ai_chatbot_easy_integration_variables['you']+'</strong> '+message+'</p>');
    }
    var psconsole = jQuery('#ai_chatbot_easy_integration_chat_dialog_container');
    if(psconsole.length)
       psconsole.scrollTop(psconsole[0].scrollHeight - psconsole.height());
  jQuery('#ai_chatbot_easy_integration_msg').focus();
    if(resturl.search('/wp-json/')>0) seperator='?';
    
    $.ajax({
    url: ai_chatbot_easy_integration_variables['resturl']+'ai_chatbot_easy_integration/v1/processchat/'+seperator+'_wpnonce='+ai_chatbot_easy_integration_variables['nonce']+'&message='+message+'&sessionid='+sessionid+'&faq='+faq+'&agent='+agent+'&openai_action='+openai_action,
    async: true, 
    dataType: "html",
    error: function(e){ console.log(' fail chat processing ');},
    success: 
    function(data){
        console.log(data);
        if('texttospeech' === openai_action){
            ai_chatbot_easy_integration_process_text_to_speech_actions(data);
        }else{
        jQuery("#ai_chatbot_easy_integration_chat_status_message").html(ai_chatbot_easy_integration_variables['message_updated']+' '+data);
        
        jQuery("#ai_chatbot_easy_integration_chat_dialog_container").append(data);
        var psconsole = jQuery('#ai_chatbot_easy_integration_chat_dialog_container');
        if(psconsole.length)
           psconsole.scrollTop(psconsole[0].scrollHeight - psconsole.height());
        }
    }
    });

});	



  // process text to speech
  function ai_chatbot_easy_integration_process_text_to_speech_actions(source){
        if($('#ai_chatbot_easy_integration_player').length){
            $('#ai_chatbot_easy_integration_player').remove();
        }
        var player = '<audio controls id="ai_chatbot_easy_integration_player" >'+source+'</audio>';
        $('body').append(player);
        setTimeout(() => {
        var x = document.getElementById("ai_chatbot_easy_integration_player");
        x.play();
       
      }, 500);
  }
});