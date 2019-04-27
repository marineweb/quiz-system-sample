$.fn.Quiz = function(configuration){
    //Global reference
    var self = this;	
    //Configuration
    self.configuration = configuration;
    //Defaults
    if (configuration.editMode == "") {
        //documentId
        configuration.mode = "answer";
    }
    //Seconds Passed
    self.secondsPassed = 0;
    //Mode
    self.mode = configuration.mode;
    //Counter for secondsPassed
    setInterval(function() {
        self.secondsPassed++;
    }, 1000);
    //Constructor
    self.constructor = function() {
		//Loading message
		$(self).html("...Quiz System is loading...");
		//Make the AJAX Call
        $.ajax({
            "url": "/lib_ajax.php",
            "data": {
                "documentId":configuration.documentId,
                "action": "takeQuiz"
            },
            "success": function(quiz) {
				//HTML Foundation for Quiz system
                $(self).html(quiz.content);

				setInterval(function(){
					//console.log();
					//Unbind click event for 
					$(self).find("#convertToQuizButton").unbind();
					//If there are no questions, allow the #quickQuestionCreator
					$(self).find("#convertToQuizButton").click(self.convertListsToQuiz);						
					//Show #quickQuestionCreator
					
					if(Library.userData.account_type == "Firm User"){
						$(self).find("#quickQuestionCreator").hide();										
					}
					else{
						$(self).find("#quickQuestionCreator").show();										
					}					
				},500);
				//Bind events to .question
				$(self).find(".removeQuestion").click(function() {
					$(this).closest(".question").remove();
				});				
				//Question Template
				self.questionTemplate = $(self).find(".question").remove().clone(true, true);			
				//Show the questions with renderQuizQuestions
				self.renderQuizQuestions(function(){
					//Edit Mode
					if (self.mode == "edit") {
						//Hide .quizInfo form fields
						$(self).find(".quizInfo").hide();
						//Default #quizTitle if not filled
						try {
							if ($(self).find("#quizTitle").val().length == 0) {
								$(self).find("#quizTitle").val($("#title").text() + " Quiz");
							}
						}
						catch (e) {

						}
						//Add Question click event
						$(self).find("#addQuestion").show().unbind().click(function() {
							//Clone the questionTemplate
							var newQuestion = self.questionTemplate.clone(true, true);
							//Append to page
							$(self).find("#quizQuestions").append(newQuestion);
							//Bind Question Events
							self.bindQuestionEvents(newQuestion);
							//Update the .questionNumber
							self.updateQuestionNumbers();
						});
						//Clear label
						$(self).find("#saveChanges").empty().append(
							$("<i>").addClass("fa fa-check")
						)
						$(self).find("#saveChanges").append(" Save Changes to Quiz");
						
						//Hide #saveChanges button if the documentId is 0 (that is, form-only mode)
						if (configuration.documentId == 0) {
							$(self).find("#saveChanges").hide();
							$(self).find("#toggleQuizMode").hide();
						}
					}
					else {
						//Clear label
						$(self).find("#saveChanges").empty().append(
							$("<i>").addClass("fa fa-check")
						);
						
						$(self).find("#saveChanges").append(" Submit Answers to Quiz");
					}				
				});
				//
				$(self).show();
            }
        });		
    };
	//Convert Lists to Quiz
	self.convertListsToQuiz = function(){
		$("#quickQuestionCreator").find(".aQuestion").each(function(i) {
			//newQuestion Object
			var newQuestionObject = {
				"content": $(this).val(),
				"answer": $("#quickQuestionCreator").find(".answer").eq(i).val(),
				"type": $("#quickQuestionCreator").find(".questionType").eq(i).val()
			};
			//Click #addQuestion
			$("#addQuestion").click();
			//Grab the last #quizQuestions .question
			var lastQuestion = $("#quizQuestions .question").last();
			//Set .questionType
			$(lastQuestion).find(".questionType").val(newQuestionObject.type).change();
			//Set question .questionContent
			$(lastQuestion).find(".questionContent").val(newQuestionObject.content);
			//Set question .questionAnswerArea
			switch(newQuestionObject.type){
				case "multiple_choice":
					//Answer parts
					var answerParts = newQuestionObject.answer.split(",");
					//Make sure we got something
					if (answerParts.length > 0) {
						//Loop-de-loop                  	
						$(lastQuestion).find(".answerChoices input").each(function(j) {
							$(this).val(answerParts[j]);
						});
					}
					//Mark the first one as correct
					$(lastQuestion).find(".multipleChoiceItem").first().click();
				break;
				case "true_false":
					if((newQuestionObject.answer == "true") || (newQuestionObject.answer == "false")){
						$(lastQuestion).find(".trueFalseItem[choice='"+ newQuestionObject.answer +"']").click();
					}
					else{
						alert("You provided an invalid true/false answer, '" + newQuestionObject.answer + "' to '" + newQuestionObject.content +"', so nothing was checked");
					}
				break;
				default:
					console.log($(lastQuestion).find(".questionAnswerArea"),newQuestionObject.answer);
					$(lastQuestion).find(".questionAnswerArea").find("input,textarea").val(newQuestionObject.answer);
				break;
			}
		});
	};
    //Render Quiz Questions
    self.renderQuizQuestions = function(callBack) {
        //Show time
        $.ajax({
            "url": "/quiz_ajax?action=extractQuiz",
			"data":{
				"mode":self.mode,
				"documentId":configuration.documentId
			},
            "success": function(response) {
                //Clear the deck
                $(self).find("#quizQuestions").empty();
				//If they already took the quiz, let them know
                if ((response.alreadyTookQuiz == true) && (response.alreadyTookQuiz == true)) {
                    $(self).empty().append(
                        $("<center>").text("--- You've already taken this quiz ---").css("padding","8px")
                    );

                    return;
                }
				else{
					//Quiz Info
					if ((response.quizInfo) || (configuration.documentId == 0)) {
						self.quizId = response.quizInfo.quiz_id;
						$(self).find("#quizType").val(response.quizInfo.quiz_type);
						$(self).find("#quizTitle").val(response.quizInfo.quiz_title);
						$(self).find("#minimumScore").val(response.quizInfo.minimum_score);
						$(self).find("#completionPercent").val(response.quizInfo.completion_percent);
						//Loop throuh the response's quizQuestions
						$.each(response.quizQuestions, function(i, currentQuestion) {
							//Get a deep clone of the questionTemplate as our new quizQuestion
							var quizQuestion = self.questionTemplate.clone(true, true).attr(currentQuestion);
							//Zebra Stripes
							if ((i % 2) == 0) {
								$(quizQuestion).css("background-color", "#f7f7f7");
							}
							else {
								$(quizQuestion).css("background-color", "white");
							}
							//Clone the questionTemplate
							$(self).find("#quizQuestions").append(quizQuestion);
							//Bind Question Events
							self.bindQuestionEvents(quizQuestion);
							//Update the .questionNumber
							self.updateQuestionNumbers();
							
							//Set .questionType and then let the element know there was a .change(), lol...code-sentences are fun. I'm having a great day.
							$(quizQuestion).find(".questionType").val(currentQuestion.question_type).change();
							//Make a .change()
							$(quizQuestion).find(".questionContent").val(currentQuestion.question_content).change();
							//Fix jQuery timing issue
							setTimeout(function() {
								//Handle different question types
								$.each(currentQuestion.question_answers, function(i, currentAnswer) {
									switch (currentQuestion.question_type) {
										case "essay":
											$(quizQuestion).find(".answerChoices").text(currentAnswer.answer_text).attr(currentAnswer);
										break;
										case "text":
											$(quizQuestion).find(".answerChoices").attr("value", currentAnswer.answer_text).attr(currentAnswer);
										break;
										case "true_false":
											//Find the $(quizQuestion)'s .trueFalseItem with a choice attribute that matches currentAnswer.answer_text attribute...went a bit far and wide for this one.
											var trueFalseItem = $(quizQuestion).find("i.trueFalseItem[choice='" + currentAnswer.answer_text + "']").attr(currentAnswer);
											//If the currentAnswer.correct_flag is checked and we're in edit mode..
											if ((currentAnswer.correct_flag == 1) && (self.mode == "edit")) {
												//click() the correct $(trueFalseItem)
												$(trueFalseItem).click();
											}
										break;
										case "fill_in_blank":
											$(quizQuestion).find(".answerChoices").attr("value", currentAnswer.answer_text).attr(currentAnswer);
										break;
										case "multiple_choice":
											//Fill in the answer_text
											var currentInput = $(quizQuestion).find("input.answerChoices:eq(" + i + ")").val(currentAnswer.answer_text);
											//.attr(currentAnswer)
											//Then find the $(quizQuestion)'s .multipleChoiceItem with a choice attribute that matches $(currentInput)'s choice attribute...went a bit far and wide for this one.
											var multipleChoiceItem = $(quizQuestion).find("i.multipleChoiceItem[choice='" + $(currentInput).attr("choice") + "']").attr(currentAnswer);
											//If the currentAnswer.correct_flag is checked and we're in edit mode..
											if ((currentAnswer.correct_flag == 1) && (self.mode == "edit")) {
												//click() the correct $(multipleChoiceItem)
												$(multipleChoiceItem).click();
											}
										break;
									}
								});

								//callBack();
							}, 500);
						});

						//Different modes
						if (self.mode == "edit") {
							//Sortable init
							$(self).find("#quizQuestions").sortable({
								"update": function() {
									//Update the .questionNumber
									self.updateQuestionNumbers();
								}
							});
						}
						else { //So...answer mode						
							//Hide .typeColumn
							$(self).find(".typeColumn").hide();
							//Remove #addQuestion, which is a button
							$(self).find("#addQuestion").hide();
							//Remove ...ummm, .removeQuestion, which is a button. Oh dear; this is rather embarassing.
							$(self).find(".removeQuestion").hide();
							//Change the font on .questionNumber(s)
							$(self).find(".questionNumber").css("font-size", "14px");
							//Loop through each .question
							$(self).find(".question").each(function() {
								//Transform Text Answer
								$(this).find(".textAnswer").val("");
								//Transform Essay Answer
								$(this).find(".essayAnswer").val("");
								//Transform .questionType
								$(this).find(".questionType").parent().text($(".questionType").val());
								//Transform .questionContent
								$(this).find(".questionContent").parent().text($(".questionContent").val());
								//Transform multiple choice items
								$(this).find("input[choice='a'],input[choice='b'],input[choice='c'],input[choice='d']").attr("disabled", "true").css({
									"border": "none",
									"background": "none"
								});
							});
						}

					}
					else {
						$(self).find("#quizArea").empty().append(
							$("<center>").css("font-style", "italic").text("--- This quiz is not available for your account ---")
						);
					}

					callBack();
				}
            }
        });
        //#saveChanges click event
        $(self).find("#saveChanges").unbind().click(function() {
            if (confirm("Finalize this quiz?")) {			
				//Edit Mode
                if (self.mode == "edit") {
					var quizData = {
						"quizId": self.quizId,
						"quizType": $(self).find("#quizType").val(),
						"quizTitle": $(self).find("#quizTitle").val(),
						"documentId": configuration.documentId,
						"minimumScore": $(self).find("#minimumScore").val(),
						"completionPercent": $(self).find("#completionPercent").val(),
						"quizData": JSON.stringify(self.extractQuestionList())
					};
					//Loading message
					$(self).html("...Submitting Quiz Answers...");				
                    //Show time
                    $.ajax({
                        "url": "/quiz_ajax?action=updateQuiz",
                        "data": quizData,
                        "success": function(response) {
                            //Refresh
                            self.constructor();
                        }
                    });
                }
				//Answer Mode
                else {
                    //Empty quizResponse
                    var quizResponse = {};
                    //Loop through every .question
                    $(self).find(".question").each(function() {
                        //Question Answer variable
                        var questionAnswer = null;
                        //Handle every .question_type
                        switch ($(this).attr("question_type")) {
                            case "text":
                                questionAnswer = $(this).find(".textAnswer").val();
                                break;
                            case "fill_in_blank":
                                questionAnswer = $(this).find(".textAnswer").val();
                                break;
                            case "essay":
                                questionAnswer = $(this).find(".essayAnswer").val();
                                break;
                            case "true_false":
                                questionAnswer = $(this).find(".fa-check-circle-o").attr("choice");
                                break;
                            case "multiple_choice":
                                questionAnswer = $(this).find(".fa-check-circle-o").attr("choice");
                                break;
                        }
                        //Store in quizResponse
                        quizResponse[$(this).attr("question_id")] = questionAnswer;
                    });

					var answerData = {
						"secondsPassed": self.secondsPassed,
						"documentId": configuration.documentId,
						"quizResponse": JSON.stringify(quizResponse)
					};
					//Loading message
					$(self).html("...Submitting Quiz Answers...");									
                    //Do the thing
                    $.ajax({
                        "url": "/quiz_ajax?action=answerQuiz",
                        "data": answerData,
                        "success": function(response) {
                            //Reset secondsPassed
                            self.secondsPassed = 0;
                            //Show if pass or fail
                            if (response.passedQuiz == true) {
                                //Track Event
                                try {
                                    Intercom("trackEvent", "answer-quiz:" + configuration.documentId);
                                }
                                catch (e) {
                                    console.log("Oh dearie me. It appears Intercom is not available.");
                                }

                                alert("Quiz Submitted!");
                                $(self).hide(500);
								self.constructor();
                                // Call a callback function if it exists
                                if (typeof quiz_completed == 'function') {
                                    quiz_completed(response.passedQuiz);
                                }
                            }
                            else {
                                alert("Didn't quite make it. Try again?");
                            }
                        }
                    });
                }
            }
        });
    };
    //Extract Question List
    self.extractQuestionList = function() {
        //Array for questionList
        var questionList = new Array();
        //Loop through every .question
        $(self).find(".question").each(function() {
            //Grab the currentQuestion. We will need it
            var currentQuestion = $(this);
            //questionData object
            var questionData = {
                "question_id": $(this).attr("question_id"),
                "type": $(this).find(".questionType").val(),
                "content": $(currentQuestion).find(".questionContent").val()
            };
            //Handle each .questionType for the right answer
            switch (questionData.type) {
                case "text":
                    questionData.answer = {
                        "text": $(this).find(".answerChoices").val(),
                        "answer_choice_id": $(this).find(".answerChoices").attr("answer_choice_id")
                    }
				break;
                case "fill_in_blank":
                    questionData.answer = {
                        "text": $(this).find(".answerChoices").val(),
                        "answer_choice_id": $(this).find(".answerChoices").attr("answer_choice_id")
                    }
				break;
                case "essay":
                    questionData.answer = {
                        "essay": $(this).find(".answerChoices").val(),
                        "answer_choice_id": $(this).find(".answerChoices").attr("answer_choice_id")
                    }
				break;
                case "true_false":
                    questionData.answer = {};
                    $(this).find(".trueFalseItem").each(function() {
                        questionData.answer[$(this).attr("choice")] = {
                            "correct": $(this).hasClass("fa-check-circle-o"),
                            "answer_choice_id": $(this).attr("answer_choice_id"),
                            "value": $(this).attr("choice")
                        }
                    });
				break;
                case "multiple_choice":
                    questionData.answer = {};
                    $(this).find(".multipleChoiceItem").each(function() {
                        questionData.answer[$(this).attr("choice")] = {
                            "correct": $(this).hasClass("fa-check-circle-o"),
                            "answer_choice_id": $(this).attr("answer_choice_id"),
                            "value": $(currentQuestion).find("input[choice='" + $(this).attr("choice") + "']").val()
                        }
                    });
				break;
            }
            //Push questionData to questionList
            questionList.push(questionData);
        });
        //Send it all back
        return questionList;
    };
    //Bind Question Events
    self.bindQuestionEvents = function(currentQuestion) {
        //Bind events to .question
        $(currentQuestion).find(".removeQuestion").unbind().click(function() {
            //Target Question
            var targetQuestion = $(this).closest(".question");
            //Confirmation
            if (confirm("Are you sure you'd like to remove this question?")) {
                if ($(targetQuestion).attr("question_id")) {
                    //Make the AJAX Call
                    $.ajax({
                        "url": "/quiz_ajax?action=removeQuestion&questionId=" + $(targetQuestion).attr("question_id"),
                        "success": function() {
                            //Not in the db yet, so just remove it
                            $(targetQuestion).remove();
                            //Update the .questionNumber
                            self.updateQuestionNumbers();
                        }
                    });
                }
                else {
                    //Not in the db yet, so just remove it
                    $(targetQuestion).remove();
                    //Update the .questionNumber
                    self.updateQuestionNumbers();
                }
            }
        });
        //Bind event to .questionType
        $(currentQuestion).find(".questionType").unbind().change(function() {
            //Grab the questionAnswerArea
            var questionAnswerArea = $(currentQuestion).find(".questionAnswerArea").empty();
            //Switch statement to handle different .questionType val()ues ...heheh, code-talking. This is fun. I'm having a great day.
            switch ($(this).val()) {
                case "text":
                    $(questionAnswerArea).append(
                        $("<input>").width("96%").attr({
                            "type": "text",
                            "placeholder": "Answer",
                            "class": "form-control answerChoices textAnswer"
                        })
                    );
				break;
                case "fill_in_blank":
                    $(questionAnswerArea).append(
                        $("<input>").width("96%").attr({
                            "type": "text",
                            "placeholder": "Answer",
                            "class": "form-control answerChoices textAnswer"
                        })
                    );
				break;
                case "essay":
                    $(questionAnswerArea).append(
                        $("<textarea>").width("96%").attr({
                            "placeholder": "Answer",
                            "class": "form-control answerChoices essayAnswer"
                        })
                    );
				break;
                case "true_false":
                    var trueFalseContainer = $("<div>").addClass("answerChoices");
                    //True
                    $(trueFalseContainer).append(
                        $("<i>").attr("choice", "true").addClass("fa fa-circle-o trueFalseItem").css({
                            "font-size": "24px",
                            "position": "relative",
                            "top": "4px",
                            "margin-right": "4px"
                        })
                    );
                    $(trueFalseContainer).append(
                        $("<span>").text("True")
                    );
                    $(trueFalseContainer).append(
                        $("<br>")
                    );
                    //False
                    $(trueFalseContainer).append(
                        $("<i>").attr("choice", "false").addClass("fa fa-circle-o trueFalseItem").css({
                            "font-size": "24px",
                            "position": "relative",
                            "top": "4px",
                            "margin-right": "4px"
                        })
                    );
                    $(trueFalseContainer).append(
                        $("<span>").text("False")
                    );
                    $(trueFalseContainer).append(
                        $("<br>")
                    );
                    //Add to page
                    $(questionAnswerArea).append(trueFalseContainer);
                    //Click event for every .trueFalseItem
                    $(currentQuestion).find(".trueFalseItem").unbind().click(function() {
                        //Make everything else wrong
                        $(currentQuestion).find(".trueFalseItem").removeClass("fa-check-circle-o").addClass("fa-circle-o");
                        //If $(this) clicked one is wrong, then make it right
                        if ($(this).hasClass("fa-circle-o")) {
                            $(this).removeClass("fa-circle-o").addClass("fa-check-circle-o");
                        }
                        //Otherwise make $(this) clicked one wrong
                        else {
                            $(this).removeClass("fa-check-circle-o").addClass("fa-circle-o");
                        }
                    });
				break;
                case "multiple_choice":
                    var radioContainer = $("<div>").addClass("answerChoices");
                    //A
                    $(radioContainer).append(
                        $("<i>").attr("choice", "a").addClass("fa fa-circle-o multipleChoiceItem").css({
                            "font-size": "24px",
                            "position": "relative",
                            "top": "4px",
                            "margin-right": "4px"
                        })
                    );
                    $(radioContainer).append(
                        $("<input>").width("90%").css({
                            "border-radius": "4px",
                            "border": "solid 1px grey"
                        }).attr({
                            "choice": "a",
                            "type": "text",
                            "placeholder": "Choice A",
                            "class": "answerChoices"
                        })
                    );
                    $(radioContainer).append(
                        $("<br>")
                    );
                    //B
                    $(radioContainer).append(
                        $("<i>").attr("choice", "b").addClass("fa fa-circle-o multipleChoiceItem").css({
                            "font-size": "24px",
                            "position": "relative",
                            "top": "4px",
                            "margin-right": "4px"
                        })
                    );
                    $(radioContainer).append(
                        $("<input>").width("90%").css({
                            "border-radius": "4px",
                            "border": "solid 1px grey"
                        }).attr({
                            "choice": "b",
                            "type": "text",
                            "placeholder": "Choice B",
                            "class": "answerChoices"
                        })
                    );
                    $(radioContainer).append(
                        $("<br>")
                    );
                    //C
                    $(radioContainer).append(
                        $("<i>").attr("choice", "c").addClass("fa fa-circle-o multipleChoiceItem").css({
                            "font-size": "24px",
                            "position": "relative",
                            "top": "4px",
                            "margin-right": "4px"
                        })
                    );
                    $(radioContainer).append(
                        $("<input>").width("90%").css({
                            "border-radius": "4px",
                            "border": "solid 1px grey"
                        }).attr({
                            "choice": "c",
                            "type": "text",
                            "placeholder": "Choice C",
                            "class": "answerChoices"
                        })
                    );
                    $(radioContainer).append(
                        $("<br>")
                    );
                    //D
                    $(radioContainer).append(
                        $("<i>").attr("choice", "d").addClass("fa fa-circle-o multipleChoiceItem").css({
                            "font-size": "24px",
                            "position": "relative",
                            "top": "4px",
                            "margin-right": "4px"
                        })
                    );
                    $(radioContainer).append(
                        $("<input>").width("90%").css({
                            "border-radius": "4px",
                            "border": "solid 1px grey"
                        }).attr({
                            "choice": "d",
                            "type": "text",
                            "placeholder": "Choice D",
                            "class": "answerChoices"
                        })
                    );
                    $(radioContainer).append(
                        $("<br>")
                    );
                    //Add to page
                    $(questionAnswerArea).append(radioContainer);
                    //Click event for every .multipleChoiceItem
                    $(currentQuestion).find(".multipleChoiceItem").unbind().click(function() {
                        //Make everything else wrong
                        $(this).siblings().removeClass("fa-check-circle-o").addClass("fa-circle-o");
                        //If $(this) clicked one is wrong, then make it right
                        if ($(this).hasClass("fa-circle-o")) {
                            $(this).removeClass("fa-circle-o").addClass("fa-check-circle-o");
                        }
                        //Otherwise make $(this) clicked one wrong
                        else {
                            $(this).removeClass("fa-check-circle-o").addClass("fa-circle-o");
                        }
                    });
				break;
            }
        });
        //Get things started for .questionType
        $(currentQuestion).find(".questionType").change();
    };
    //Update Question Numbers
    self.updateQuestionNumbers = function() {
        $(self).find("#quizQuestions .question").each(function(questionNumber, questionObject) {
            questionNumber = (questionNumber + 1);
            $(questionObject).find(".questionNumber").text(questionNumber + ".)");
        });
    };
	
    //Showtime
    $(document).ready(self.constructor);
	
	return self;
};