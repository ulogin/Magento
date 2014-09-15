if ( (typeof jQuery === 'undefined') && !window.jQuery ) {
    document.write(unescape("%3Cscript type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js'%3E%3C/script%3E%3Cscript type='text/javascript'%3EjQuery.noConflict();%3C/script%3E"));
} else {
    if((typeof jQuery === 'undefined') && window.jQuery) {
        jQuery = window.jQuery;
    } else if((typeof jQuery !== 'undefined') && !window.jQuery) {
        window.jQuery = jQuery;
    }
}


function uloginCallback(token){
    jQuery.ajax({
        url: '/ulogin/ajax/addaccount/',
        type: 'POST',
        dataType: 'json',
        cache: false,
        data: {token: token},
        success: function (data) {
            switch (data.answerType) {
                case 'error':
                    uloginMessage(data.title, data.msg, data.answerType);
                    break;
                case 'ok':
                    if (jQuery('#ulogin_accounts').length > 0){
                        addUloginNetworkBlock(data.user.network, data.userId, data.title, data.msg);
                    } else {
                        location.reload();
                    }
                    break;
                case 'verify':
                    // Верификация аккаунта
                    uLogin.mergeAccounts(token);
                    break;
                case 'merge':
                    // Синхронизация аккаунтов
                    uLogin.mergeAccounts(token, data.existIdentity);
                    break;
            }
        }
    });
}

function uloginMessage(title, msg, answerType) {
    var mess = (title != '') ? '<b>' + title + '</b>' : '';
    mess += (mess != '') ? '<br>' : '';
    mess += (msg != '') ? msg : '';
    var class_msg = '';
    switch (answerType) {
        case 'error':
            class_msg = 'error-msg';
            break;
        case 'ok':
            class_msg = 'success-msg';
            break;
    }
    mess = '<li class="' + class_msg + '"><ul><li><span>' + mess + '</span></li></ul></li>';
    if (jQuery('.messages').length > 0) {
        jQuery('.messages').html(mess + jQuery('.messages').html());
    } else {
        mess = '<ul class="messages">' + mess + '</ul>';
        jQuery('.my-account').before(mess);
    }

}


function uloginDeleteAccount(network){
    jQuery.ajax({
        url: '/ulogin/ajax/deleteaccount/',
        type: 'POST',
            dataType: 'json',
        cache: false,
        data: {
            delete_account: 'delete_account',
            network: network
        },
        success: function (data) {
            switch (data.answerType) {
                case 'error':
                    uloginMessage(data.title, data.msg, data.answerType);
                    break;
                case 'ok':
                    if (jQuery('#ulogin_accounts #ulogin_'+network).length > 0){
                        jQuery('#ulogin_accounts #ulogin_'+network).hide();
                        uloginMessage(data.title, '', data.answerType);
                    }
                    break;
            }
        }
    });
}


function addUloginNetworkBlock(network, userId, title, msg) {
    if (jQuery('#ulogin_accounts #ulogin_'+network).length == 0) {
        jQuery('#ulogin_accounts').replaceWith(
            '<div id="ulogin_accounts">' +
            jQuery('#ulogin_accounts').html() +
            '<div id="ulogin_' + network + '" class="ulogin_btn" ' +
            'onclick="uloginDeleteAccount(\'' + userId + '\', \'' + network + '\')">' +
            '</div>' +
            '</div>');
        uloginMessage(title, '', 'ok');
    } else {
        if (jQuery('#ulogin_accounts #ulogin_'+network).is(':hidden')) {
            uloginMessage(title, '', 'ok');
        }
        jQuery('#ulogin_accounts #ulogin_'+network).show();
    }
}