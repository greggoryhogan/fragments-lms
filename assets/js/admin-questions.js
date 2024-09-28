(function($) {

    //get exam type
    var exam_type = '';
    if($('#flms_exam_type').length) {
        exam_type = $('#flms_exam_type').val();
        show_exam_question_fields();
        $(document).on('change','#flms_exam_type', function() {
            exam_type = $('#flms_exam_type').val();
            show_exam_question_fields();
        });

        function show_exam_question_fields() {
            if(exam_type == 'cumulative') {
                //$('#sample-draw-exam-questions').addlass('is-inactive');
                $('#standard-exam-questions').addClass('is-inactive');
                $('#category-same-draw-questions').addClass('is-inactive');
                $('#cumulative-exam-questions').removeClass('is-inactive');
            } else if( exam_type == 'category-sample-draw') {
                $('#cumulative-exam-questions').addClass('is-inactive');
                $('#standard-exam-questions').addClass('is-inactive');
                $('#category-same-draw-questions').removeClass('is-inactive');
            } else {
                $('#category-same-draw-questions').addClass('is-inactive');
                $('#cumulative-exam-questions').addClass('is-inactive');
                $('#standard-exam-questions').removeClass('is-inactive');
            }
            if(exam_type == 'sample-draw') {
                $('.sample-draw-exam-option').show();
            } else {
                $('.sample-draw-exam-option').hide();
            }
        }
    }

    $(document).on('click', '.exam-question .actions .toggle', function() {
        $(this).closest('.exam-question').toggleClass('hide-answer');
    });

    $(document).on('click', '.exam-question .actions .remove', function() {
        var question = $(this).closest('.exam-question');
        question.toggleClass('to-be-removed');
        if(question.hasClass('to-be-removed')) {
            question.find('input').attr('name',question.find('input').attr('name').replace('selected','deselected'));
        } else {
            question.find('input').attr('name',question.find('input').attr('name').replace('deselected','selected'));
        }
    });

    //toggle meta box content 
    $(document).on('change','input[name="flms_question_type"]',function(){
        $('.answer-option').removeClass('is-active');
        var type = $(this).val();
        if(type == 'multiple-choice') {
            $('#answer-type-single-choice').addClass('is-active');
        } else {
            $('#answer-type-'+type).addClass('is-active');
        }
    });

    //Add new option to field
    $(document).on('click','#add-answer-option',function(e) {
        e.preventDefault();
        var type = 'single-choice';
        var clone = $('#input-clone.type-'+type).html();
        var cloneindex = $('.answer-options-container .answer-input').length + 1;
        $('.answer-options-container').append('<div class="answer-input type-'+type+' is-clone">'+clone+'</div>');
        //update val index
        $('.is-clone').find('input[name="question-correct"]').val(cloneindex);
        $('.is-clone').find('textarea').attr('name',type+'-content[]');
        $('.is-clone').removeClass('is-clone');
        //see if we can remove
        if($('.answer-options-container .answer-input').length > 1) {
            $('.answer-options-container .answer-input .remove').each(function() {
                $(this).addClass('is-active');
            });
        } else {
            $('.answer-input .remove').removeClass('is-active');
        }
    });
    
    $(document).on('click','.answer-options-container .answer-input .remove', function(e) {
        e.preventDefault();
        $(this).closest('.answer-input').remove();
        toggleRemoveOption();
    });

    //Show remove option
    function toggleRemoveOption() {
        if($('.answer-options-container .answer-input').length > 1) {
            $('.answer-options-container .answer-input .remove').each(function() {
                $(this).addClass('is-active');
            });
        } else {
            $('.answer-options-container .answer-input .remove').each(function() {
                $(this).removeClass('is-active');
            });
        }
    }
    toggleRemoveOption();

    

    //Sync single and multiple choice options
    var previous = $('input[name="flms_question_type"]:checked').val();
    $('input[name="flms_question_type"]').on('change',function() {
        var current = $(this).val();
        if(current == 'multiple-choice') {
            previous = 'multiple-choice';
            //check single choice for values
            $('#answer-type-single-choice input[type="radio"]').each(function() {
                $(this).attr('type','checkbox');
                $(this).attr('name','question-correct[]');
            });
        } else if (current == 'single-choice' && previous == 'multiple-choice') {
            previous = 'single-choice';
            if($('#answer-type-single-choice .answer-options-container input[type="checkbox"]:checked').length > 1) {
                $('#answer-type-single-choice .answer-options-container input[type="checkbox"]:checked').not(':last-of-type').each(function() {
                    $(this).prop("checked", false);
                });
            }
            $('#answer-type-single-choice input[type="checkbox"]').each(function() {
                $(this).attr('type','radio');
                $(this).attr('name','question-correct');
            });
        }
    });

    function sort_flms_questions() {
        /**
         * Sorting Questions
         */
        if($('.sortable-questions').length) {
            $('#selected-questions').sortable({
                connectWith: ".exam-question",
                forcePlaceholderSize: true,
                placeholder: "ui-sortable-placeholder",
                receive: function(ev, ui) {
                    /*$(ui.sender).addClass('is-active');
                    console.log(ui);
                    $(ui.helper).find('input').attr('name','selected-flms-questions[]'); 
                    */
                },
                update: function( event, ui ) {
                    /*$(this + ' .exam-question').each(function() {
                        if($(this).hasClass('to-be-removed')) {
                            $(this).find('input').attr('name','deselected-flms-exams[]');
                        } else {
                            $(this).find('input').attr('name','selected-flms-exams[]');    
                        }
                    });*/
                
                }
            });
            /*$('#question-bank .exam-question').draggable({
                connectToSortable: "#selected-questions",
                revert: "invalid",
                helper: "clone",
                cancel: ".is-active"
            });*/
        }
        if($('.answer-options-container').length) {
            $('.answer-options-container.single-choice, .answer-options-container.multiple-choice').sortable({
                connectWith: ".answer-input",
                forcePlaceholderSize: true,
                placeholder: "ui-sortable-placeholder",
                receive: function(ev, ui) {
                    /*$(ui.sender).addClass('is-active');
                    console.log(ui);
                    $(ui.helper).find('input').attr('name','selected-flms-questions[]'); 
                    */
                },
                update: function( event, ui ) {
                    /*$(this + ' .exam-question').each(function() {
                        if($(this).hasClass('to-be-removed')) {
                            $(this).find('input').attr('name','deselected-flms-exams[]');
                        } else {
                            $(this).find('input').attr('name','selected-flms-exams[]');    
                        }
                    });*/
                
                }
            });
            /*$('#question-bank .exam-question').draggable({
                connectToSortable: "#selected-questions",
                revert: "invalid",
                helper: "clone",
                cancel: ".is-active"
            });*/
        }
    }
    sort_flms_questions();

    //Questions ajax pagination
    $(document).on('click','.questions-pagination a', function(e) {
        e.preventDefault();
        var page = $( this ).attr( 'data-page' );
        var current_questions = [];
        if($('#selected-questions .exam-question').length) {
            $('#selected-questions .exam-question').each(function() {
                current_questions.push($(this).attr('data-id'));
            });
        }
        $.ajax({
            url: flms_admin_questions.ajax_url,
            type: 'get',
            data: {
                action : 'get_questions_page',
                page: page,
                current_questions : current_questions,
            },
            success: function(data) {
                $('#question-bank').html(data.questions_html);
                sort_flms_questions();
            }
        });
    
    });

    $(document).on('click','.questions-toggle .toggle-title',function() {
        //$('.toggle-title, .question-option').removeClass('is-active');
        $(this).parent().find('.question-option').toggleClass('is-active');
        $(this).parent().find('.toggle-title').toggleClass('is-active');
    });

    $('#add-question-to-bank').on('click',function(e) {
        e.preventDefault();
        var questions = [];
        $('#question-bank input[type="checkbox"]:checked').each(function() {
            if(!$(this).parent().parent().hasClass('is-active')) {
                questions.push($(this).val());
                $(this).parent().parent().addClass('is-active');
            }
        });
        $.ajax({
            url: flms_admin_questions.ajax_url,
            type: 'get',
            data: {
                action : 'add_questions_to_bank',
                questions : questions,
            },
            success: function(data) {
                $('#selected-questions').append(data.html);
                sort_flms_questions();
            }
        });
    });

    //search questions
    var newsearch = '';
    $(document).on('keyup','#search-questions-input', function(e) {
        e.preventDefault();
        setTimeout( function() {
            var searchterm = $('#search-questions-input').val();
            if (searchterm !== '' && searchterm !== newsearch) {
                newsearch = searchterm;
                $('#searched-questions').html('');
                search_flms_questions(searchterm, 1);
            }
        }, 500);
    });

    function search_flms_questions(searchterm, page) {
        if(newsearch == searchterm) {
            $.ajax({
                url: flms_admin_questions.ajax_url,
                dataType: 'json',
                data: {
                    action : 'search_questions',
                    searchterm : searchterm,
                    page: page, // Initial page
                },
                success: function(data) {
                    if(page == 1) {
                        $('#searched-questions').html(data.results);
                        if(data.total_pages >= 1) {
                            $('#add-searched-question-to-bank').removeClass('is-inactive');
                        } else {
                            $('#add-searched-question-to-bank').addClass('is-inactive');
                        }
                    } else {
                        $('#searched-questions').append(data.results);
                    }
                    if (data.total_pages > 1 && page < data.total_pages) {
                        var nextpage = page + 1;
                        search_flms_questions(searchterm, nextpage)
                    }
                    
                }
            });
        }
    }

    $('#add-searched-question-to-bank').on('click',function(e) {
        e.preventDefault();
        var questions = [];
        $('#searched-questions input[type="checkbox"]:checked').each(function() {
            questions.push($(this).val());
        });
        $.ajax({
            url: flms_admin_questions.ajax_url,
            type: 'get',
            data: {
                action : 'add_questions_to_bank',
                questions : questions,
            },
            success: function(data) {
                $('#selected-questions').append(data.html);
                $('#searched-questions input[type="checkbox"]:checked').each(function() {
                    $(this).prop('checked', false);
                });
                sort_flms_questions();
            }
        });
    });

    $('#add-categories-to-bank').on('click',function(e) {
        e.preventDefault();
        var current_questions = [];
        if($('#selected-questions .exam-question').length) {
            $('#selected-questions .exam-question').each(function() {
                current_questions.push($(this).attr('data-id'));
            });
        }
        var question_categories = [];
        $('#question-categories input[type="checkbox"]:checked').each(function() {
            if(!$(this).parent().hasClass('is-active')) {
                question_categories.push($(this).val());
                $(this).parent().addClass('is-active');
            }
        });
        //console.log(current_questions);
        $.ajax({
            url: flms_admin_questions.ajax_url,
            type: 'get',
            data: {
                action : 'add_question_categories_to_bank',
                current_questions : current_questions,
                question_categories : question_categories
            },
            success: function(data) {
                $('#selected-questions').append(data.html);
                current_questions = [];
                if($('#selected-questions .exam-question').length) {
                    $('#selected-questions .exam-question').each(function() {
                        current_questions.push($(this).attr('data-id'));
                    });
                }
                $('#question-bank input[type="checkbox"]').each(function() {
                    if($.inArray($(this).val(), current_questions) != -1) {
                        $(this).prop('checked','checked');
                        $(this).parent().parent().addClass('is-active');
                    }
                });
                sort_flms_questions();
            }
        });
    });

    

})( jQuery );