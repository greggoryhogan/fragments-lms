(function($) {

    //init text editors to wpeditors in admin
    $('.answer-option textarea').each(function() {
        var id = $(this).attr('id');
        settings = { tinymce: true, quicktags: true } 
        wp.editor.initialize(id, settings);
    });

    //set our defaults for exam processing
    var question_counter_array = [];
    var current_exam_page = 1;
    var exam_question_counter = 0;

    var review = 0;
    var reset_exam_timer = 0;
    //start the exam
    $(document).on('click','#start_exam',function(e) {
        e.preventDefault();
        reset_exam_timer = 1;
        get_paginated_exam_questions(exam_question_counter, 1, 1,  review);
    });

    //resume the exam
    $(document).on('click','#resume_exam',function(e) {
        e.preventDefault();
        review = 0;
        reset_exam_timer = 1;
        get_paginated_exam_questions(exam_question_counter, 1, 0,  review);
    });

     //review the exam
     $(document).on('click','#review_exam',function(e) {
        e.preventDefault();
        review = 1;
        reset_exam_timer = 0;
        get_paginated_exam_questions(exam_question_counter, 1, 1,  review);
    });

    $(document).on('click','#complete-review', function(e) {
        //$('#exam-into-content').show();
        window.location = window.location;
        //$('#current-exam').hide();
    });

    var progressSaved = true;
    $(document).on('change','.flms-question input', function() {
        progressSaved = false;
    });

    //prev/next exam buttons
    $(document).on('click','.exam-pagination',function(e) {
        e.preventDefault();
        //see if we're moving forwards or backwards
        if($(this).hasClass('previous')) {
            current_exam_page = current_exam_page - 1;
            let obj = question_counter_array.find(o => o.page === current_exam_page);
            var question_counter = obj.count;
        } else {
            current_exam_page += 1;
            var question_counter = exam_question_counter
        }
        reset_exam_timer = 0;
        get_paginated_exam_questions(question_counter, current_exam_page, 0, review);
        let examTop = $("#current-exam").offset().top - 50;
        window.scrollTo(0, examTop);
    });

    var isSaving = false;
    $(document).on('click','#save_exam', function() {
        reset_exam_timer = 1;
        var answers = flms_get_exam_answers();
        $('.flms-question').addClass('is-loading');
        isSaving = true;
        $.ajax({
            url: flms_exams.ajax_url,
            type: 'get',
            data: {
                action: 'save_exam',
                exam_id : flms_exams.exam_id,
                version_index : flms_exams.version_index,
                user_id : flms_exams.current_user_id,
                answers : answers
            },
            success: function(data) {
                isSaving = false;
                progressSaved = true;
                window.location = window.location;
                $('.flms-question').removeClass('is-loading');
            },
        });
    });

    //dummy complete exam
    $(document).on('click','#submit_exam', function() {
        submitExam();
    });

    
    function submitExam() {
        var answers = flms_get_exam_answers();
        $('.flms-question').addClass('is-loading');
        isSaving = true;
        $.ajax({
            url: flms_exams.ajax_url,
            type: 'get',
            data: {
                action: 'grade_exam',
                exam_id : flms_exams.exam_id,
                version_index : flms_exams.version_index,
                user_id : flms_exams.current_user_id,
                answers : answers
            },
            success: function(data) {
                //update exam question counter
                //exam_question_counter = data.exam_question_count;
                //alert('Exam complete!');
                //console.log(answers);
                isSaving = false;
                progressSaved = true;
                //$('.flms-question').removeClass('is-loading');
                if(data.redirect != '') {
                    window.location = data.exam_redirect;
                } else {
                    window.location = window.location;
                }
            },
        });
    }

    $(document).on('click','#update-exam-user-answers', function() {
        var answers = flms_get_exam_answers();
        $('.flms-question').addClass('is-loading');
        isSaving = true;
        $.ajax({
            url: flms_exams.ajax_url,
            type: 'get',
            data: {
                action: 'grade_exam',
                exam_id : flms_exams.exam_id,
                version_index : flms_exams.version_index,
                user_id : flms_exams.current_user_id,
                answers : answers,
                exam_update : 1
            },
            success: function(data) {
                //update exam question counter
                //exam_question_counter = data.exam_question_count;
                //alert('Exam complete!');
                //console.log(answers);
                isSaving = false;
                progressSaved = true;
                //$('.flms-question').removeClass('is-loading');
                if(data.redirect != '') {
                    window.location = data.exam_redirect;
                } else {
                    window.location = window.location;
                }
            },
        });
    });

    //ajax call to get the page's exam questions
    function get_paginated_exam_questions(question_counter, page, reset_exam_progress, review) {
        var answers = flms_get_exam_answers();
        $('.flms-question').addClass('is-loading');
        //console.log(review);
        $.ajax({
            url: flms_exams.ajax_url,
            type: 'get',
            data: {
                action: 'paginate_exam',
                exam_id : flms_exams.exam_id,
                page : page,
                version_index : flms_exams.version_index,
                question_counter : question_counter,
                reset_exam_progress : reset_exam_progress,
                user_id : flms_exams.current_user_id,
                answers : answers,
                review : review,
                reset_timer : reset_exam_timer
            },
            success: function(data) {
                progressSaved = true;
                //update exam question counter
                exam_question_counter = data.exam_question_count;
                //update pagination number
                current_exam_page = data.page;
                //remove any existing text editors
                $('.answer-option textarea').each(function() {
                    var id = $(this).attr('id');
                    if (typeof wp.editor != "undefined") {
                        wp.editor.remove(id);
                    }
                });
                //update content
                $('#exam-into-content').hide();
                $('.flms-course-navigation').hide();
                $('#current-exam').html(data.questions);
                //init any new text editors
                $('.answer-option textarea').each(function() {
                    var id = $(this).attr('id');
                    settings = { tinymce: true, quicktags: true } 
                    wp.editor.initialize(id, settings);
                });
                //start timer
                if(data.time_remaining >= 0) {
                    var deadline = new Date(Date.parse(new Date()) + parseInt(data.time_remaining));
                    initializeClock('exam-timer', deadline);
                } 
                //add counter data to array so we know where to reset on previous button
                var counter_data = {};
                counter_data['page'] = current_exam_page;
                counter_data['count'] = data.start_count;
                let obj = question_counter_array.find(o => o.page === data.page);
                if(obj === undefined){
                    question_counter_array.push(counter_data);
                }
            }
        });
    }

    function flms_get_exam_answers() {
        let answers = {};

        $('.flms-question').each(function() {
            var $this = $(this);
            var id = $(this).attr('data-id');
            var type = $(this).attr('data-type');
            switch (type) { 
                case 'single-choice': 
                    var val = $this.find('input:checked').val();
                    answers[id] = val;
                    /*answers.push({
                        id : id,
                        type : type, 
                        answer : val
                    });*/
                    break;
                case 'multiple-choice': 
                    var checked = [];
                    var val = $this.find('input:checked').each(function(){
                        checked.push($(this).val());
                    });
                    /*answers.push({
                        id : id,
                        type : type, 
                        answer : checked
                    });*/
                    answers[id] = checked;
                    break;
                case 'free-choice': 
                    //not active
                    break;
                case 'fill-in-the-blank': 
                    var val = $this.find('input').val();
                    /*answers.push({
                        id : id,
                        type : type, 
                        answer : val
                    });*/
                    answers[id] = val;
                    break;
                case 'assessment':
                    var val = $this.find('input:checked').val();
                    /*answers.push({
                        id : id,
                        type : type, 
                        answer : val
                    });*/
                    answers[id] = val;
                    break;
                case 'essay':
                    var wysiwyg = $this.find('.wp-editor-wrap').attr('id');
                    let elid = wysiwyg.replace('-wrap','').replace('wp-','');
                    var val = wp.editor.getContent(elid);
                    /*answers.push({
                        id : id,
                        type : type, 
                        answer : val
                    });*/
                    answers[id] = val;
                    break;
                default:
                    //nothing to do
                    break;
            }
            //alert(type);
        });

        return answers;

        
    }
    

    $(window).on('beforeunload', function(){
        if(isSaving || !progressSaved) {
            return 'You progress is being saved, are you sure you want to leave?';
        }
    }); 

    function getTimeRemaining(endtime) {
        const total = Date.parse(endtime) - Date.parse(new Date());
        const seconds = Math.floor((total / 1000) % 60);
        const minutes = Math.floor((total / 1000 / 60) % 60);
        const hours = Math.floor((total / (1000 * 60 * 60)) % 24);
        const days = Math.floor(total / (1000 * 60 * 60 * 24));
        
        return {
          total,
          days,
          hours,
          minutes,
          seconds
        };
      }
      
      var shown_exam_timer_notice = false;
      function initializeClock(id, endtime) {
        const clock = document.getElementById(id);
        const daysSpan = clock.querySelector('.days');
        const hoursSpan = clock.querySelector('.hours');
        const minutesSpan = clock.querySelector('.minutes');
        const secondsSpan = clock.querySelector('.seconds');
      
        function updateClock() {
          const t = getTimeRemaining(endtime);
          
          var daysRemaining = t.days;
          if(daysRemaining > 0) {
            daysSpan.innerHTML = daysRemaining;
            document.getElementById("timer-days").classList.remove('inactive');
          } else {
            document.getElementById("timer-days").classList.add('inactive');
          }
          var hoursRemaining = t.hours;
          if(hoursRemaining > 0) {
            hoursSpan.innerHTML = hoursRemaining;
            document.getElementById("timer-hours").classList.remove('inactive');
          } else {
            document.getElementById("timer-hours").classList.add('inactive');
          }
          var minutesRemaining = t.minutes;
          if(minutesRemaining > 0) {
            minutesSpan.innerHTML = minutesRemaining;
            document.getElementById("timer-minutes").classList.remove('inactive');
          } else {
            document.getElementById("timer-minutes").classList.add('inactive');
          }
          //var secondsRemaining = (t.seconds).slice(-2);
          secondsSpan.innerHTML = t.seconds;
      
          if (t.total <= 0) {
            clearInterval(timeinterval);
            //$('#flms-no-time-remaining').addClass('is-active');
            if(!shown_exam_timer_notice) {
                shown_exam_timer_notice = true;
                $('#exam-timer .remaining').append('<div>Time has expired. Submitting your exam...</div>');
            }
            submitExam();
          }
        }
      
        updateClock();
        const timeinterval = setInterval(updateClock, 1000);
      }

})( jQuery );