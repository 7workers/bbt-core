function disable(control) { $(control).attr('disabled', 'disabled'); }
function enable(control) { $(control).removeAttr('disabled'); }

function handleNotifications(dataFromServer, onClickCallback) {
    if (dataFromServer['NOTIFY'] !== undefined) {
        riseNotification(dataFromServer['NOTIFY'], undefined, onClickCallback);
    }

    if (dataFromServer['notify'] !== undefined) {
        riseNotification(dataFromServer['notify'], undefined, onClickCallback);
    }

    if (dataFromServer['WARNING'] !== undefined) {
        riseNotification(dataFromServer['WARNING'], 'warning', onClickCallback);
    }

    if (dataFromServer['warning'] !== undefined) {
        riseNotification(dataFromServer['warning'], 'error', onClickCallback);
    }

    if (dataFromServer['ERROR'] !== undefined) {
        riseNotification(dataFromServer['ERROR'], 'error', onClickCallback);
    }

    if (dataFromServer['error'] !== undefined) {
        riseNotification(dataFromServer['error'], 'error', onClickCallback);
    }
}

function riseNotification(text, type, onClickCallback) {

    var cssClass = '';
    var timeout = 5000;

    if( type === undefined ) {
        cssClass = 'alert-info';
    } else if( type === 'error' ) {
        cssClass = 'alert-danger';
        timeout = 10000;
    } else if( type === 'warning' ) {
        cssClass = 'alert-warning';
        timeout = 8000;
    }

    var newDiv = $('<div class="alert '+cssClass+'">'+text+'</div>');
    
    if( onClickCallback !== undefined )
    {
        newDiv.bind('click', onClickCallback);
        newDiv.addClass('cursor-pointer');
    }
    
    newDiv.bind('click', function(){
        $(this).parent().slideUp();
        $(this).parent().fadeOut();
    });

    setTimeout(function(){
        newDiv.slideUp();
        newDiv.fadeOut();
    }, timeout);
    
    $('#notificationsContainer').prepend(newDiv);
    
    newDiv.fadeIn();
    
}