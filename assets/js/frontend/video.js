(function($) {

  //youtube embed
  function loadYoutubeVideo() {
    (function loadYoutubeIFrameApiScript() {
      const tag = document.createElement("script");
      tag.src = "https://www.youtube.com/iframe_api";
  
      const firstScriptTag = document.getElementsByTagName("script")[0];
      firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
      tag.onload = setupPlayer;
    })();
  
    let player = null;
  
    function setupPlayer() {
        window.YT.ready(function() {
            player = new window.YT.Player("flms-youtube-embed", {
            height: "390",
            width: "640",
            videoId: "M7lc1UVf-VE",
            events: {
                onReady: onPlayerReady,
                onStateChange: onPlayerStateChange
            }
            });
        });
    }
  
    //autoplay
    
    function onPlayerReady(event) {
      //event.target.playVideo();
    }
    var youTubeTimer; 
    var time = 0;
    var maxTime = 0;
    function onPlayerStateChange(event) {
      var videoStatuses = Object.entries(window.YT.PlayerState);
      var currentState = videoStatuses.find(status => status[1] === event.data)[0];
      if(flms_video.settings.force_full_video == 1) {
        //Allow rewind but not ff
        if(event.data == 1) { // playing
          youTubeTimer = setInterval(function(){ 
              time = player.getCurrentTime();
          }, 100); // 100 means repeat in 100 ms
        }
        else { // not playing
          if(player.getCurrentTime() > time && time >= maxTime) {
            if(maxTime > time) {
              player.seekTo(maxTime);
            } else {
              maxTime = time;
              player.seekTo(time);
            }
            if(event.data == 2) {
              //paused
              //event.target.playVideo();
            }
            
          }
          clearInterval(youTubeTimer);
        }
        if(event.data == 0) { //ended
          enable_complete_continue();
        }
      }
    }
  }

  //Vimeo embed
  function loadVimeoVideo() {
    (function loadVimeoIFrameApiScript() {
      const tag = document.createElement("script");
      tag.src = "https://player.vimeo.com/api/player.js";
  
      const firstScriptTag = document.getElementsByTagName("script")[0];
      firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
      tag.onload = setupPlayer;
    })();
  
    let player = null;
  
    function setupPlayer() {
      const iframe = document.getElementById('flms-vimeo-embed');
      const player = new Vimeo.Player(iframe);
  
      player.on('play', function() {
        $('.flms-video').addClass('video-started');
      });

      player.on('ended', function() {
        enable_complete_continue();
        $('.flms-video').removeClass('video-started');
        $('.flms-play-pause .play-pause-btn--pause').removeClass('is-active');
        $('.flms-play-pause .play-pause-btn--play').addClass('is-active');
      });
      var maxTime = 0;
      player.on('timeupdate', function(e) {
        player.getCurrentTime().then(function(seconds) {
          if(seconds > maxTime) {
            maxTime = seconds;
          }
        });
        percent = e.percent * 100;
        $(".flms-video #seekbar span").css("width", percent+"%");
      });

      $('.flms-play-pause').on('click', function() {
        player.getPaused().then(function(paused) {
          if(paused) {
            player.play();
            $('.flms-play-pause .play-pause-btn--play').removeClass('is-active');
            $('.flms-play-pause .play-pause-btn--pause').addClass('is-active');  
          } else {
            player.pause(); 
            $('.flms-play-pause .play-pause-btn--pause').removeClass('is-active');
            $('.flms-play-pause .play-pause-btn--play').addClass('is-active');
          }
        });
        
        if($('.flms-video').hasClass('video-started')) {
          $('.flms-play-pause').addClass('is-active');
          if (!player.paused){
            setTimeout(function() {
              $('.flms-play-pause').removeClass('is-active');
            },500);
          }
        } else {
          $('.flms-play-pause').removeClass('is-active');
        }
      });
    
      $(".flms-video #seekbar").on("click", function(e){
        var offset = $(this).offset();
        var left = (e.pageX - offset.left);
        var totalWidth = $(".flms-video #seekbar").width();
        var percentage = ( left / totalWidth );
        player.getDuration().then(function(duration) {
          player.getCurrentTime().then(function(seconds) {
            var vidTime = duration * percentage;
            if(((seconds > vidTime || maxTime > vidTime) && flms_video.settings.force_full_video == 1) || flms_video.settings.force_full_video == 0) {
              player.setCurrentTime(vidTime).then(function() {
                percentage = percentage * 100;
              $(".flms-video #seekbar span").css("width", percentage+"%");
              });
            }
          });
          
          
        });
        
        
      });

      $('.flms-video #seekbar').on('mousemove', function(event) { 
        var left = event.pageX - $(this).offset().left;
        var totalWidth = $(".flms-video #seekbar").width();
        var percentage = ( left / totalWidth );
        if(percentage > 0 && percentage <= 1) {
          player.getDuration().then(function(duration) {
            var vidTime = duration * percentage;
            if(percentage < .07) {
              left = '7%'; 
            } else if(percentage > .90) {
              left = '90%'; 
            }
            $('.timefeedback').css({left: left}).html(toTime(vidTime)).show();
          });
        } else {
          $('.timefeedback').hide();
        }
        
      });
      $('.flms-video #seekbar').on('mouseleave', function() {
          $('.timefeedback').hide();
      });
      
  
    }
  }

  //Default video player (local videos)
  function loadFlmsVideo() {
    var video = document.getElementById('flms-default-embed');
    
    video.addEventListener("play", (event) => {
      if(!$('.flms-video').hasClass('video-started')) {
        video.currentTime = 0;
        $('.flms-video').addClass('video-started');
      }
    });
    var maxTime = 0;
    video.addEventListener("timeupdate", (event) => {
      if(video.currentTime >= maxTime) {
        maxTime = video.currentTime;
      }
      var percentage = ( video.currentTime / video.duration ) * 100;
      $(".flms-video #seekbar span").css("width", percentage+"%");
    });

    video.addEventListener("ended", (event) => {
      enable_complete_continue();
      $('.flms-video').removeClass('video-started');
      $('.flms-play-pause .play-pause-btn--pause').removeClass('is-active');
      $('.flms-play-pause .play-pause-btn--play').addClass('is-active');
    });

    $('.flms-play-pause').on('click', function() {
      if (video.paused){
        video.play();
        $('.flms-play-pause .play-pause-btn--play').removeClass('is-active');
        $('.flms-play-pause .play-pause-btn--pause').addClass('is-active');
      } else {
        video.pause(); 
        $('.flms-play-pause .play-pause-btn--pause').removeClass('is-active');
        $('.flms-play-pause .play-pause-btn--play').addClass('is-active');
      }
      if($('.flms-video').hasClass('video-started')) {
        $('.flms-play-pause').addClass('is-active');
        if (!video.paused){
          setTimeout(function() {
            $('.flms-play-pause').removeClass('is-active');
          },500);
        }
      } else {
        $('.flms-play-pause').removeClass('is-active');
      }
    });
    
    $(".flms-video #seekbar").on("click", function(e){
        var offset = $(this).offset();
        var left = (e.pageX - offset.left);
        var totalWidth = $(".flms-video #seekbar").width();
        var percentage = ( left / totalWidth );
        var vidTime = video.duration * percentage;
        if(((video.currentTime > vidTime || maxTime > vidTime) && flms_video.settings.force_full_video == 1) || flms_video.settings.force_full_video == 0) {
          video.currentTime = vidTime;
          percentage = percentage * 100;
          $(".flms-video #seekbar span").css("width", percentage+"%");
        }
    });

    $('.flms-video #seekbar').on('mousemove', function(event) { 
      var left = event.pageX - $(this).offset().left;
      var totalWidth = $(".flms-video #seekbar").width();
      var percentage = ( left / totalWidth );
      if(percentage > 0 && percentage <= 1) {
        var vidTime = video.duration * percentage;
        if(percentage < .07) {
          left = '7%'; 
        } else if(percentage > .90) {
          left = '90%'; 
        }
        $('.timefeedback').css({left: left}).html(toTime(vidTime)).show();
      } else {
        $('.timefeedback').hide();
      }
      
    });
    $('.flms-video #seekbar').on('mouseleave', function() {
        $('.timefeedback').hide();
    });
  }
  
  //load youtube if exists
  if($('#flms-youtube-embed').length) {
    loadYoutubeVideo();
  }

  //load youtube if exists
  if($('#flms-vimeo-embed').length) {
    loadVimeoVideo();
  }

  if($('#flms-default-embed').length) {
    loadFlmsVideo();
  }

  function toTime(seconds) {
    var date = new Date(null);
    date.setSeconds(seconds);
    return date.toISOString().substr(11, 8);
  }

  //allow complete lesson / topic button
  function enable_complete_continue() {
    $('#flms-complete-step').prop("disabled", false).removeAttr('data-flms-tooltip');
    if(flms_video.settings.autocomplete == 1) {
      $('#flms-complete-step').trigger('click');
    }
  }

})( jQuery );