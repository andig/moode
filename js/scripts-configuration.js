/*
 *  This Program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3, or (at your option)
 *  any later version.
 *
 *  This Program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with TsunAMP; see the file COPYING.  If not, see
 *  <http://www.gnu.org/licenses/>.
 *
 *	PlayerUI Copyright (C) 2013 Andrea Coiutti & Simone De Gregori
 *	Tsunamp Team
 *	http://www.tsunamp.com
 *
 *	UI-design/JS code by: 	Andrea Coiutti (aka ACX)
 *	PHP/JS code by:			Simone De Gregori (aka Orion)
 * 
 *	file:					scripts-configuration.js
 * 	version:				1.1
 *
 *	TCMODS Edition 
 *
 *	TC (Tim Curtis) 2015-04-29, r1.8
 *  - replaced existing code with code from scripts-playback.js to enable playback controls to work when on the config pages
 *	- add handler for show/hide userid and password on source mount screen based on cifs or nfs selected
 *
 *	TC (Tim Curtis) 2015-05-30, r1.9
 *  - add click handler for info show/hide toggle
 *  - add click handlers for playback history controls
 *
 *	TC (Tim Curtis) 2015-06-26, r2.0
 *  - add click handler for speed button on Customize popup
 *
 *	TC (Tim Curtis) 2015-07-31, r2.1
 *	- hide playback controls when on config pages
 *
 */

jQuery(document).ready(function($){ 'use strict';

	backendRequest(GUI.state);
	
	if (GUI.state != 'disconnected') {
	    $('#loader').hide();
    }

	// TC (Tim Curtis) 2015-07-31: hide playback controls	
	$('.playback-controls').removeClass('playback-controls-sm');
	$('.playback-controls').addClass('hidden');
	$('#playback-page-cycle').css({"display":"none"});


	/* TC (Tim Curtis) 2015-07-31: comment out
	// TC (Tim Curtis) 2015-04-29: code from scripts-playback.js to enable playback controls to work when on the config pages
    // BUTTON CLICK HANDLERS
    // TC (Tim Curtis) 2015-01-01: remove highlighting and implement play/pause toggle, stop btn code removed
    $('.btn-cmd').click(function() {
        var cmd;

        // Play/pause
        if ($(this).attr('id') == 'play') {
            if (GUI.state == 'play') {
				$("#play i").removeClass("icon-play").addClass("icon-pause"); // TC 2015-01-01
	            // TC (Tim Curtis) 2014-11-30: stop for radio station, pause for song file    
	            if (MPDCS.json.file.substr(0, 5).toLowerCase() == 'http:') {
	                cmd = 'stop';
	            } else {
	                cmd = 'pause';
	            }
                $('#countdown-display').countdown('pause');
            } else if (GUI.state == 'pause') {
	            $("#play i").removeClass("icon-pause").addClass("icon-play"); // TC 2015-01-01
                cmd = 'play';
                $('#countdown-display').countdown('resume');
            } else if (GUI.state == 'stop') {
                cmd = 'play';
                // TC (Tim Curtis) 2014-11-30: count up or down depending on conf setting, radio always counts up
                // TC (Tim Curtis) 2015-01-27: add onTick and chg format 'MS' to 'hMS'
                // TC (Tim Curtis) 2015-01-27: remove timer_knob_radiocount
				if (TCMCONF.json['time_knob_countup'] == "1" || parseInt(GUI.json['time']) == 0) {
                    $('#countdown-display').countdown({since: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				} else {
                    $('#countdown-display').countdown({until: parseInt(GUI.json['time']), onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				}

				var current = parseInt(GUI.json['song']); // TC (Tim Curtis) 2015-04-29: add scrollto
				customScroll('pl', current, 200);
            }
            window.clearInterval(GUI.currentKnob);
            sendCmd(cmd);
            return;
        }

        // Previous/next
		// TC (Tim Curtis) 2015-04-29: chg 'previous' to 'prev'
        else if ($(this).attr('id') == 'prev' || $(this).attr('id') == 'next') {
            GUI.halt = 1;
			$('#countdown-display').countdown('pause');
			window.clearInterval(GUI.currentKnob);
        }        

        // TC (Tim Curtis) 2014-11-30: for 3-button play controls, previous
		// TC (Tim Curtis) 2015-04-29: chg 'previous' to 'prev'
        if ($(this).attr('id') == 'prev' && parseInt(GUI.json['time']) > 0 && parseInt(getMpdStatus()['elapsed']) > 0) {
            refreshTimer(0, 0, 'stop'); // reset to beginning of song and pause
	        sendCmd('seek ' + GUI.json['song'] + ' ' + 0);
	        if (GUI.state != 'pause') {
		        cmd = 'pause';
			} else {
				cmd = '';		        
	        }
        } else {
        	cmd = $(this).attr('id');
		}
		sendCmd(cmd);
    });
	// TC (Tim Curtis) 2015-04-29: end code from scripts-playback.js
	*/

	// TC (Tim Curtis) 2015-04-29: show/hide userid and password fields based on select value
	if($('#type').length) {
		if ($('#type').val() == 'cifs') {
			$('#userid-password').show();
		} else {
			$('#userid-password').hide();
		}                       
		$('#type').change(function(){          
			if ($(this).val() == 'cifs') {
				$('#userid-password').show();
			} else {
				$('#userid-password').hide();
			}                       
		});
	}
	
	// show/hide DHCP static configuration based on select value
	if( $('#dhcp').length ){
		if ($('#dhcp').val() == 'false') {
			$('#network-manual-config').show();
		}                        
		$('#dhcp').change(function(){          
			if ($(this).val() == 'true') {
				$('#network-manual-config').hide();
				//console.log('true');
			}
			else {
				$('#network-manual-config').show();
				//console.log('false');
			}                                                            
		});
	}
	
	// show advanced options
	if( $('.show-advanced-config').length ){
		$('.show-advanced-config').click(function(e){
			e.preventDefault();
			if ($(this).hasClass('active'))
			{
				$('.advanced-config').hide();
				$(this).removeClass('active');
				$(this).find('i').removeClass('icon-minus-sign').addClass('icon-plus-sign');
				$(this).find('span').html('Show advanced options');
			} else {
				$('.advanced-config').show();
				$(this).addClass('active');
				$(this).find('i').removeClass('icon-plus-sign').addClass('icon-minus-sign');
				$(this).find('span').html('Hide advanced options');
			}
		});	
	}
	
	// confirm manual data
	if( $('.manual-edit-confirm').length ){
		$(this).find('.btn-primary').click(function(){
			$('#mpdconf_editor').show().removeClass('hide');
			$(this).hide();
		});
	}
	
    // TC (Tim Curtis) 2015-05-30: info show/hide toggle
    $('.info-toggle').click(function() {
		var spanId = '#' + $(this).data('cmd');
		if ($(spanId).hasClass('hide')) {
			$(spanId).removeClass('hide');
		} else {
			$(spanId).addClass('hide');
		}
    });

	// TC (Tim Curtis) 2015-05-30; plaback history first/last page click handlers
    $('.ph-firstPage').click(function(){
        $('#container-playhistory').scrollTo(0 , 500);
    });
    $('.ph-lastPage').click(function(){
        $('#container-playhistory').scrollTo('100%', 500);
    });

	// TC (Tim Curtis) 2015-06-26; customization settings first/last page click handlers
    $('.cs-firstPage').click(function(){
        $('#container-customize').scrollTo(0 , 500);
    });
    $('.cs-lastPage').click(function(){
        $('#container-customize').scrollTo('100%', 500);
    });

    // Playlist history typedown search
    $("#ph-filter").keyup(function(){
        $.scrollTo(0 , 500);
        var filter = $(this).val(), count = 0;
        $(".playhistory li").each(function(){
            if ($(this).text().search(new RegExp(filter, "i")) < 0) {
                $(this).hide();
            } else {
                $(this).show();
                count++;
            }
        });
		// TC (Tim Curtis) 2014-12-23: change format of search results line
        var s = (count == 1) ? '' : 's';
        if (filter != '') {
            $('#ph-filter-results').html((+count) + '&nbsp;item' + s);
        } else {
            $('#ph-filter-results').html('');
        }
    });

});
