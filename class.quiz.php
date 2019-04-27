<?php
	//Quiz Class
	class _Quiz{
		//Constructor
		function __construct($requestData){
			//JSON Response
			$this->jsonResponse = array();
		}

		//Remove Question
		function removeQuestion($requestData){
			global $AI;
			//Delete ai_quiz_answer_choices
			$AI->db->query("
				DELETE	FROM ai_quiz_answer_choices
				WHERE 	ai_quiz_answer_choices.question_id = '{$requestData["questionId"]}'
			");
			//Delete ai_quiz_questions
			$this->jsonResponse["status"] = $AI->db->query("
				DELETE 	FROM ai_quiz_questions
				WHERE 	ai_quiz_questions.question_id = '{$requestData["questionId"]}'
				LIMIT	1
			");
		}
		//Extract Quiz
		function extractQuiz($requestData){
			global $AI;
			$quizQuestions = array();
			$documentId = $requestData["documentId"];

			$quizTaken = db_fetch_assoc($AI->db->query("
				SELECT 	ai_quiz_submissions.quiz_id
				FROM  	ai_quiz_submissions
				WHERE  	ai_quiz_submissions.quiz_id = '{$documentId}'
				AND  	ai_quiz_submissions.userID = '{$AI->user->userID}'
				LIMIT 	1
			"));

			if((@$quizTaken["quiz_id"]) && ($requestData["mode"] == "answer")){
				$this->jsonResponse["quizInfo"] = false;
				$this->jsonResponse["alreadyTookQuiz"] = true;
			}
			else{
				$this->jsonResponse["alreadyTookQuiz"] = false;
				//Grab the $quizInfo. We will need them.
				$this->jsonResponse["quizInfo"] = @db_fetch_assoc($AI->db->query("
					SELECT	ai_quiz.*
					FROM	ai_quiz
					WHERE	ai_quiz.document_id = '{$documentId}'
					LIMIT	1
				"));

				if($this->jsonResponse["quizInfo"] == false){

					$documentData = @db_fetch_assoc($AI->db->query("
						SELECT	lib_docs.*
						FROM	lib_docs
						WHERE	lib_docs.id = '{$documentId}'
						LIMIT	1
					"));

					//Create the quiz
					$quizQuery = "
						INSERT INTO ai_quiz (
							document_id,
							quiz_title,
							quiz_type,
							minimum_score,
							completion_percent
						)
						VALUES (
							'".db_in($documentData["id"])."',
							'".db_in($documentData["title"])."',
							'scored',
							'0',
							'0'
						);
					";

					$AI->db->query($quizQuery);

					//Grab the $quizInfo. We will need them.
					$this->jsonResponse["quizInfo"] = @db_fetch_assoc($AI->db->query("
						SELECT	ai_quiz.*
						FROM	ai_quiz
						WHERE	ai_quiz.document_id = '{$documentId}'
						LIMIT	1
					"));
				}
				//Grab the $quizQuestions. We will need them
				$queryHandle = $AI->db->query("
					SELECT		ai_quiz_questions.question_id,
								ai_quiz_questions.question_type,
								ai_quiz_questions.question_content
					FROM		ai_quiz_questions
					WHERE		ai_quiz_questions.document_id = '{$documentId}'
					ORDER BY	ai_quiz_questions.question_number
				");
				//Loop
				while($currentQuestion = db_fetch_assoc($queryHandle)){
					$currentQuestion["question_answers"] = $this->extractQuestionAnswers($currentQuestion["question_id"]);
					$quizQuestions[] = $currentQuestion;
				}

				$this->jsonResponse["quizQuestions"] = $quizQuestions;
			}
		}
		//Extract Quiz Answers
		function extractQuestionAnswers($questionId){
			global $AI;
			$questionAnswers = array();
			$answerQueryHandle = $AI->db->query("
				SELECT		ai_quiz_answer_choices.answer_choice_id,
							ai_quiz_answer_choices.answer_text,
							ai_quiz_answer_choices.correct_flag
				FROM		ai_quiz_answer_choices
				WHERE		ai_quiz_answer_choices.question_id = '{$questionId}'
				ORDER BY	ai_quiz_answer_choices.answer_choice_id
			");

			while($currentAnswer = db_fetch_assoc($answerQueryHandle)){
				$questionAnswers[] = $currentAnswer;
			}

			return $questionAnswers;
		}
		//Answer Quiz
		function answerQuiz($requestData){
			global $AI;
			//Grab the $quizData
			$quizAnswers = json_decode(stripslashes($requestData["quizResponse"]),true);
			//Make sure we have something
			if(sizeof($quizAnswers) > 0){
				$documentId = $requestData["documentId"];
				//Number Correct
				$numberCorrect = 0;
				//Grab the $totalQuestions
				$totalQuestions = @end(db_fetch_assoc($AI->db->query("
					SELECT COUNT(ai_quiz_questions.question_id)
					FROM 	ai_quiz_questions
					WHERE	ai_quiz_questions.document_id = '{$documentId}'
				")));
				//Quiz Data
				$quizData = @db_fetch_assoc($AI->db->query("
					SELECT 	ai_quiz.*
					FROM 	ai_quiz
					WHERE	ai_quiz.document_id = '{$documentId}'
					LIMIT	1
				"));

				//Loop...
				foreach($quizAnswers as $questionId => $userAnswers){
					//Grab the $expectedAnswers
					$expectedAnswers = $this->extractQuestionAnswers($questionId);
					//Make sure we always have an array for $expectedAnswers
					if(!is_array($expectedAnswers)){
						$expectedAnswers = array($expectedAnswers);
					}
					//Make sure we have something
					if(sizeof($expectedAnswers) > 0){
						foreach($expectedAnswers as $answerKey => $currentExpectedAnswer){

							$recordedAnswer = json_encode($userAnswers);
							//Store answers
							$storeAnswerQuery = "
								INSERT INTO ai_quiz_submission_answers
								(`id`, `userID`, `quizID`, `questionID`, `questionAnswer`) VALUES
								(NULL, '{$AI->user->userID}', '{$quizData["quiz_id"]}', '{$questionId}', '{$recordedAnswer}');

							";

							$AI->db->query($storeAnswerQuery);

							$multipleChoiceKeys = array("a" => 0,"b" => 1,"c" => 2,"d"=> 3);
							$multipleChoiceAnswer = @$multipleChoiceKeys[$userAnswers];
							//Multiple choice
							if(isset($multipleChoiceAnswer)){
								if($answerKey == $multipleChoiceAnswer){
									if($currentExpectedAnswer["correct_flag"] == 1){
										//echo "$userAnswers is CORRECT\n";
										$numberCorrect++;
									}
									else{
										//echo "$userAnswers is INCORRECT\n";
									}
								}
							}
							else{
								$totalCorrectUserWords = 0;
								$userAnswers = strtolower($userAnswers);
								$currentExpectedAnswer = strtolower($currentExpectedAnswer["answer_text"]);
								$totalExpectedAnswerWords = sizeof(explode(" ",$currentExpectedAnswer));
								foreach(explode(" ",$userAnswers) as $x => $currentWord){
									if(@strpos($currentExpectedAnswer,$currentWord) >= 0){
										$totalCorrectUserWords++;
									}
								}

								$correctPercentage = ($totalCorrectUserWords / $totalExpectedAnswerWords) * 100;

								if($correctPercentage > 10){
									//echo "$userAnswers is CORRECT\n";
									$numberCorrect++;
								}
								else{
									//echo "$userAnswers is INCORRECT\n";
								}
							}
						}
					}
				}
				//Do the thing
				$AI->db->query("
					INSERT INTO ai_quiz_submissions(
						ai_quiz_submissions.submission_id ,
						ai_quiz_submissions.quiz_id,
						ai_quiz_submissions.userID,
						ai_quiz_submissions.date_started ,
						ai_quiz_submissions.date_ended ,
						ai_quiz_submissions.number_correct ,
						ai_quiz_submissions.total_questions ,
						ai_quiz_submissions.perc_required
					)
					VALUES (
						NULL,
						'{$documentId}',
						'{$AI->user->userID}',
						DATE_SUB(NOW(), INTERVAL {$requestData["secondsPassed"]} SECOND),
						NOW(),
						'{$numberCorrect}',
						'{$totalQuestions}',
						''
					);
				");
				//Track Quiz
				util_track('quiz_submission','user',$AI->user->userID,0,$documentId);
				//Passed Quiz Flag
				$passedQuiz = null;
				//Check to make sure the completion percentage is good
				if($quizData["completion_percent"] > 0){
					$passedQuiz = (sizeof($quizAnswers) == $totalQuestions);
				}
				else{
					$passedQuiz = true;
				}
				//Check to make sure the minimum sore is met
				if($quizData["minimum_score"] > 0){
					$passedQuiz = ((int)$numberCorrect >= $quizData["minimum_score"]);
				}

				//Send it all back
				$this->jsonResponse = array(
					"passedQuiz" => $passedQuiz,
					"numberCorrect" => (int)$numberCorrect,
					"totalQuestions" => (int)$totalQuestions
				);
			}
		}
		//Update Quiz
		function updateQuiz($requestData){
			global $AI;
			//Grab the $quizData
			$quizData = json_decode(stripslashes($requestData["quizData"]),true);

			if(@$requestData["quizId"]){
				//Create the quiz
				$quizQuery = "
					UPDATE		ai_quiz
					SET			ai_quiz.quiz_title = '".db_in($requestData["quizTitle"])."',
								ai_quiz.quiz_type = '".db_in($requestData["quizType"])."',
								ai_quiz.minimum_score = '".db_in($requestData["minimumScore"])."',
								ai_quiz.completion_percent = '".db_in($requestData["completionPercent"])."'
					WHERE		ai_quiz.quiz_id = '".db_in($requestData["quizId"])."'
					AND			ai_quiz.document_id = '".db_in($requestData["documentId"])."'
					LIMIT		1
				";
			}
			else{
				//Create the quiz
				$quizQuery = "
					INSERT INTO ai_quiz (
						document_id,
						quiz_title,
						quiz_type,
						minimum_score,
						completion_percent
					)
					VALUES (
						'".db_in($requestData["documentId"])."',
						'".db_in($requestData["quizTitle"])."',
						'".db_in($requestData["quizType"])."',
						'".db_in($requestData["minimumScore"])."',
						'".db_in($requestData["completionPercent"])."'
					);
				";
			}

			$AI->db->query($quizQuery);

			if(sizeof($quizData) > 0){
				//Loop through the $quizData
				foreach($quizData as $i => $currentQuestion){
					$questionNumber = ($i + 1);

					if(@$currentQuestion["question_id"]){
						//Create the question
						$questionQuery = "
							UPDATE 	ai_quiz_questions
							SET		ai_quiz_questions.question_number = '".db_in($questionNumber)."',
									ai_quiz_questions.question_type = '".db_in($currentQuestion["type"])."',
									ai_quiz_questions.question_content = '".db_in($currentQuestion["content"])."'
							WHERE	ai_quiz_questions.question_id = '".db_in($currentQuestion["question_id"])."'
						";
					}
					else{
						//Create the question
						$questionQuery = "
							INSERT INTO ai_quiz_questions (
								document_id,
								question_number,
								question_type,
								question_content
							)
							VALUES (
								'".db_in($requestData["documentId"])."',
								'".db_in($questionNumber)."',
								'".db_in($currentQuestion["type"])."',
								'".db_in($currentQuestion["content"])."'
							);
						";
					}

					//echo $questionQuery;
					//Do the thing
					$AI->db->query($questionQuery);
					//Make sure we have answers
					if(sizeof($currentQuestion["answer"]) > 0){
						//Set the $lastQuestionId
						if(@$currentQuestion["question_id"]){
							$lastQuestionId = $currentQuestion["question_id"];
						}
						else{
							//Grab the $lastQuestionId. We will need it
							$lastQuestionId = @end(db_fetch_assoc($AI->db->query("SELECT MAX(ai_quiz_questions.question_id) FROM ai_quiz_questions")));
						}
						//Handle different question types
						switch($currentQuestion["type"]){
							case "text":
								$currentAnswer = $currentQuestion["answer"];
								$currentAnswer["text"] = mysql_real_escape_string($currentAnswer["text"]);
								if(@$currentAnswer["answer_choice_id"]){
									$questionQuery = "
										UPDATE	ai_quiz_answer_choices
										SET		ai_quiz_answer_choices.answer_text = '{$currentAnswer["text"]}',
												ai_quiz_answer_choices.correct_flag = '1'
										WHERE	ai_quiz_answer_choices.answer_choice_id = '{$currentAnswer["answer_choice_id"]}'
									";
								}
								else{
									$questionQuery = "
										INSERT INTO ai_quiz_answer_choices (
											question_id ,
											answer,
											answer_text,
											correct_flag
										)
										VALUES (
											{$lastQuestionId},
											'',
											'{$currentAnswer["text"]}',
											1
										);
									";
								}
								//Do the thing
								$AI->db->query($questionQuery);
								//echo($questionQuery);
							break;
							case "fill_in_blank":
								$currentAnswer = $currentQuestion["answer"];

								//print_r($currentAnswer);

								$currentAnswer["text"] = mysql_real_escape_string($currentAnswer["text"]);
								if(@$currentAnswer["answer_choice_id"]){
									$questionQuery = "
										UPDATE	ai_quiz_answer_choices
										SET		ai_quiz_answer_choices.answer_text = '{$currentAnswer["text"]}',
												ai_quiz_answer_choices.correct_flag = '1'
										WHERE	ai_quiz_answer_choices.answer_choice_id = '{$currentAnswer["answer_choice_id"]}'
									";
								}
								else{
									$questionQuery = "
										INSERT INTO ai_quiz_answer_choices (
											question_id ,
											answer,
											answer_text,
											correct_flag
										)
										VALUES (
											{$lastQuestionId},
											'',
											'{$currentAnswer["text"]}',
											1
										);
									";
								}
								//Do the thing
								$AI->db->query($questionQuery);
								//echo($questionQuery);
							break;
							case "essay":
								$currentAnswer = $currentQuestion["answer"];

								$currentAnswer["essay"] = mysql_real_escape_string($currentAnswer["essay"]);

								if(@$currentAnswer["answer_choice_id"]){
									$questionQuery = "
										UPDATE	ai_quiz_answer_choices
										SET		ai_quiz_answer_choices.answer_text = '{$currentAnswer["essay"]}',
												ai_quiz_answer_choices.correct_flag = '1'
										WHERE	ai_quiz_answer_choices.answer_choice_id = '{$currentAnswer["answer_choice_id"]}'
									";
								}
								else{
									$questionQuery = "
										INSERT INTO ai_quiz_answer_choices (
											question_id ,
											answer,
											answer_text,
											correct_flag
										)
										VALUES (
											{$lastQuestionId},
											'',
											'{$currentAnswer["essay"]}',
											1
										);
									";
								}
								//Do the thing
								$AI->db->query($questionQuery);
								//echo($questionQuery);
							break;
							case "multiple_choice":
								//Make sure we have a list of answers
								foreach($currentQuestion["answer"] as $x => $currentAnswer){
									$currentAnswer["correct"] = ($currentAnswer["correct"] == true) ? 1 : 0;
									$currentAnswer["value"] = mysql_real_escape_string($currentAnswer["value"]);
									if(@$currentAnswer["answer_choice_id"]){
										$questionQuery = "
											UPDATE	ai_quiz_answer_choices
											SET		ai_quiz_answer_choices.answer_text = '{$currentAnswer["value"]}',
													ai_quiz_answer_choices.correct_flag = '{$currentAnswer["correct"]}'
											WHERE	ai_quiz_answer_choices.answer_choice_id = '{$currentAnswer["answer_choice_id"]}'
										";
									}
									else{
										$questionQuery = "
											INSERT INTO ai_quiz_answer_choices (
												question_id ,
												answer,
												answer_text,
												correct_flag
											)
											VALUES (
												{$lastQuestionId},
												'',
												'{$currentAnswer["value"]}',
												'{$currentAnswer["correct"]}'
											);
										";
									}
									//Do the thing
									$AI->db->query($questionQuery);
									//echo($questionQuery);
								}
							break;
							case "true_false":
								//Make sure we have a list of answers
								foreach($currentQuestion["answer"] as $x => $currentAnswer){

									//print_r($currentAnswer);

									$currentAnswer["correct"] = ($currentAnswer["correct"] == true) ? 1 : 0;
									$currentAnswer["value"] = mysql_real_escape_string($currentAnswer["value"]);
									if(@$currentAnswer["answer_choice_id"]){
										$questionQuery = "
											UPDATE	ai_quiz_answer_choices
											SET		ai_quiz_answer_choices.answer_text = '{$currentAnswer["value"]}',
													ai_quiz_answer_choices.correct_flag = '{$currentAnswer["correct"]}'
											WHERE	ai_quiz_answer_choices.answer_choice_id = '{$currentAnswer["answer_choice_id"]}'
										";
									}
									else{
										$questionQuery = "
											INSERT INTO ai_quiz_answer_choices (
												question_id ,
												answer,
												answer_text,
												correct_flag
											)
											VALUES (
												{$lastQuestionId},
												'',
												'{$currentAnswer["value"]}',
												'{$currentAnswer["correct"]}'
											);
										";
									}
									//Do the thing
									$AI->db->query($questionQuery);
									//echo($questionQuery);
								}
							break;
						}
					}
				}

				$_SESSION["libraryContributorAutoSave"] = null;
				unset($_SESSION["libraryContributorAutoSave"]);
				$this->jsonResponse["status"] = true;
			}
		}
		//Draw Quiz
		function drawQuiz($quizId,$mode,$fTable, $fField, $fKey){
			return "				
				<form action='#' class='form-horizontal ws-validate' style = 'padding:8px;'>
					<div style = 'display:none;'>
						<div class='form-group quizInfo'>
							<label class='control-label'>Title</label>
							<input type = 'text' class = 'form-control' id = 'quizTitle' placeholder = 'Enter the title of the quiz' />
						</div>
						<div class='form-group quizInfo'>
							<label class='control-label'>Type:</label>
							<select class = 'form-control' id = 'quizType'>
								<option value = 'survey'>Survey</option>
								<option value = 'scored' selected>Scored</option>
								<option value = 'show_score'>Show Score</option>
								<option value = 'require_score'>Require Score</option>
							</select>
						</div>
						<div class='form-group quizInfo'>
							<label class='control-label'>Minimum Score</label>
							<input type = 'text' class = 'form-control' id = 'minimumScore' value = '0' placeholder = 'Enter the minimum score' />
						</div>
						<div class='form-group quizInfo'>
							<label class='control-label'>Completion Percent</label>
							<input type = 'text' class = 'form-control' id = 'completionPercent'  value = '100' placeholder = 'Enter the completion percent' />
						</div>
					</div>
					
					<div id = 'quickQuestionCreator' class = 'row' style = 'display:none;'>
						<div class='form-group col-md-4 '>
							<label class='control-label'>Type</label>
							<select class='form-control questionType'>
								<option value='text' SELECTED>Text</option>
								<option value='essay'>Essay</option>
								<option value='true_false'>True/False</option>
								<option value='fill_in_blank'>Fill in the blank</option>
								<option value='multiple_choice'>Multiple Choice</option>
							</select>
							<select class='form-control questionType'>
								<option value='text'>Text</option>
								<option value='essay' SELECTED>Essay</option>
								<option value='true_false'>True/False</option>
								<option value='fill_in_blank'>Fill in the blank</option>
								<option value='multiple_choice'>Multiple Choice</option>
							</select>
							<select class='form-control questionType'>
								<option value='text'>Text</option>
								<option value='essay'>Essay</option>
								<option value='true_false' SELECTED>True/False</option>
								<option value='fill_in_blank'>Fill in the blank</option>
								<option value='multiple_choice'>Multiple Choice</option>
							</select>
							<select class='form-control questionType'>
								<option value='text'>Text</option>
								<option value='essay'>Essay</option>
								<option value='true_false'>True/False</option>
								<option value='fill_in_blank' SELECTED>Fill in the blank</option>
								<option value='multiple_choice'>Multiple Choice</option>
							</select>
							<select class='form-control questionType'>
								<option value='text'>Text</option>
								<option value='essay'>Essay</option>
								<option value='true_false'>True/False</option>
								<option value='fill_in_blank'>Fill in the blank</option>
								<option value='multiple_choice' SELECTED>Multiple Choice</option>
							</select>							
						</div>
						<div class='form-group col-md-4'>
							<label class='control-label'>Questions</label>
							<input class = 'form-control aQuestion' type = 'text' placeholder = 'Type your question' />
							<input class = 'form-control aQuestion' type = 'text' placeholder = 'Type your question' />
							<input class = 'form-control aQuestion' type = 'text' placeholder = 'Type your question' />
							<input class = 'form-control aQuestion' type = 'text' placeholder = 'Type your question' />
							<input class = 'form-control aQuestion' type = 'text' placeholder = 'Type your question' />							
						</div>
						<div class='form-group col-md-4'>
							<label class='control-label'>Answers</label>
							<input class = 'form-control answer' type = 'text' placeholder = 'Type an answer' />
							<input class = 'form-control answer' type = 'text' placeholder = 'Type an answer' />
							<input class = 'form-control answer' type = 'text' placeholder = 'Type an answer' />
							<input class = 'form-control answer' type = 'text' placeholder = 'Type an answer' />
							<input class = 'form-control answer' type = 'text' placeholder = 'Type an answer' />														
						</div>					
						<div class = 'form-group col-md-12' style = 'text-align:center;'>
							<em>
								Type 'true' or 'false' on True/False question type.</br>
								Separate multiple choice question answers with a comma. First multiple choice answer will be marked as correct, but you can change this later.
							</em>
						</div>
						<div class = 'form-group col-md-12' style = 'text-align:center;'>
							<div class = 'btn btn-warning' id = 'convertToQuizButton'>
								<i class = 'fa fa-list'></i> Convert to Quiz
							</div>
						</div>
					</div>

					
					<div class='form-group' style = 'border-top:dashed 1px grey;margin-top:8px;padding-top:8px;'>
						<div class = 'col-md-1'>
							<i id = 'addQuestion' class = 'fa fa-plus-circle' style = 'font-size:32px;color:green;float:left;cursor:pointer;' />
						</div>
						<div class = 'col-md-2 typeColumn' style = 'font-size:20px;'>
							Type
						</div>
						<div class = 'col-md-4 answerColumn' style = 'font-size:20px;'>
							Question
						</div>
						<div class = 'col-md-4' style = 'font-size:20px;'>
							Answer
						</div>
						<div class = 'col-md-1'>

						</div>
					</div>
					<div class='form-group' id = 'quizQuestions'>
						<div class = 'question row' style = 'width:100%;padding-top:4px;padding-bottom:4px;border-radius:3px;'>
							<div class = 'col-md-1'>
								<span class = 'questionNumber' style = 'float:left;'></span>
							</div>
							<div class = 'col-md-2 typeColumn'>
								<select class = 'form-control questionType'>
									<option value = 'text'>Text</option>
									<option value = 'essay'>Essay</option>
									<option value = 'true_false'>True/False</option>
									<option value = 'fill_in_blank'>Fill in the blank</option>
									<option value = 'multiple_choice'>Multiple Choice</option>
								</select>
							</div>
							<div class = 'col-md-4 answerColumn'>
								<input type = 'text' class = 'form-control questionContent' placeholder = 'Question Content' />
							</div>
							<div class = 'col-md-4 questionAnswerArea'>

							</div>
							<div class = 'col-md-1'>
								<i class = 'fa fa-minus-circle removeQuestion' style = 'font-size:32px;color:red;float:right;cursor:pointer;' />
							</div>
						</div>
					</div>
					<div class='form-group'>
						<div id = 'saveChanges' class='btn btn-success'>Save Changes</button>
					</div>
				</form>
			";
		}
	}
?>
