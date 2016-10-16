define([
	'scm','jquery',
	'//www.youtube.com/iframe_api'
	],function(SCM,$){

	var id="SCMYoutube",
		callback, finishCallback, player, intervalId,
		playObserve, volumeObserve, positionObserve;

	$(document.body).prepend(
	'<div id="video-container"><div><div id="'+id+'"></div></div></div>');

	window.onYouTubeIframeAPIReady = function(){
		player = new YT.Player(id,{
			// Custom player vars
			playerVars: {
				controls: 0,
				showinfo: 0,
				iv_load_policy: 3
			},
			width: '100%',
			height: 'auto',
			events: {
				onReady:function(){
					callback({on:on,off:off});
				},
				onStateChange:stateChange,
				onError:error
			}
		});
	}

	function on(url,onFinish){
		finishCallback = onFinish;
		playObserve = SCM.isPlay.subscribe(play);
		volumeObserve = SCM.volume.subscribe(volume);
		positionObserve = SCM.seekPosition.subscribe(position);
		intervalId = setInterval(interval,100);

		var videoId = parseVideoId(url);

		// Begin custom event code
		var changeEvent = new CustomEvent("player-change", { "detail": videoId});
		var pageFrame = document.getElementById('content').contentWindow;
		pageFrame.document.dispatchEvent(changeEvent);
		// End custom event code

		player.setVolume(SCM.volume());

		if(SCM.isPlay()) {
			player.loadVideoById(videoId);
		}
		else
			player.cueVideoById(videoId);
	}
	function off(){
		playObserve.dispose();
		volumeObserve.dispose();
		positionObserve.dispose();
		clearInterval(intervalId);

		//pause video as off
		play(0);
	}
	function play(value){
		if(value) player.playVideo();
		else player.pauseVideo();
	}
	function volume(value){
		player.setVolume(value);
	}
	function position(value){
		player.seekTo(value);
	}
	function interval(){
		SCM.loadedFraction(player.getVideoLoadedFraction());
		SCM.position(player.getCurrentTime());
		SCM.duration(player.getDuration());
	}
	function stateChange(e){
		switch(e.data){
			case 0:finishCallback(); break;
		}
	}
	function error(e){
		var msg = 'Youtube Error '+e.data;
		switch(e.data){
			case 5: msg += ': HTML5 Error'; break;
			case 2: msg += ': Invalid Link'; break;
			case 101: msg += ': Cannot be played in embedded player'; break;
			case 100: msg += ': Request not Found'; break;
		}
		if(e.data!=5) SCM.message(msg);
	}
	function parseVideoId(url){
		var prefix = '(v=|/v/|youtu.be/)';
		return url
			.match(new RegExp(prefix+'.*'))[0]
			.replace(new RegExp(prefix),'')
			.substr(0,11);
	}
	return {
		load:function(name, req, onLoad, config){
			callback = onLoad;
		}
	};
});

