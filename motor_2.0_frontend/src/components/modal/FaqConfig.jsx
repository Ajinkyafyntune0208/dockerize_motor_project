import Card from "components/GlobalCard/Card";
import { getFaq, postFaq } from "modules/Home/home.slice";
import React, { useEffect, useState } from "react";
import { Col, Form, Row } from "react-bootstrap";
import { useForm } from "react-hook-form";
import { useDispatch, useSelector } from "react-redux";
import styled from "styled-components";
import swal from "sweetalert";
import _ from "lodash";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import {
  categoriesOfBike,
  categoriesOfCar,
  categoriesOfCv,
  categoriesOfOther,
  journey_type,
} from "./helper";

const yupValidate = yup.object({
  question: yup.string().required("Question is required"),
  answer: yup.string().required("Answer is required"),
  journey_type: yup.string().required("Journey Type is required"),
  category: yup.string().required("Category is required"),
});

const QuestionForm = () => {
  const { faq } = useSelector((state) => state.home);
  const { register, handleSubmit, errors, watch, setValue, reset } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "onBlur",
    reValidateMode: "onBlur",
  });

  const prev_question =
    !_.isEmpty(faq) && faq?.content ? JSON.parse(faq?.content) : [];

  const [journey, setJourney] = useState("");
  const [category, setCategory] = useState("");
  const [selectedQuestionIndex, setSelectedQuestionIndex] = useState(null);

  const dispatch = useDispatch();

  useEffect(() => {
    dispatch(getFaq());
  }, [dispatch]);

  useEffect(() => {
    if (category?.length > 0 && prev_question?.length > 0) {
      const filteredQuestions = prev_question.filter(
        (question) => question.category === category
      );
      filteredQuestions.forEach((question, index) => {
        setValue(`question_${index}`, question.question);
        setValue(`answer_${index}`, question.answer);
      });
    }
  }, []);

  const onSubmit = (data) => {
    const newQuestion = {
      question: data.question,
      answer: data.answer,
      category: data.category,
      journeyType: journey,
      questionId: prev_question.length + 1,
    };

    let updatedQuestions = [...prev_question, newQuestion];

    dispatch(postFaq({ data: { content: JSON.stringify(updatedQuestions) } }))
      .then(() => {
        reset();
        swal("Success", "Question Added Successfully", "success").then(() => {
          dispatch(getFaq());
        });
      })
      .catch((error) => {
        swal("Error", error, "error");
      });
  };

  const handleDeleteQuestion = (questionId) => {
    const updatedQuestions = [...prev_question];
    const filteredQuestions = updatedQuestions.filter(
      (q) => q.questionId !== questionId
    );

    dispatch(postFaq({ data: { content: JSON.stringify(filteredQuestions) } }))
      .then(() => {
        reset();
        swal("Success", "Question Deleted Successfully", "success").then(() => {
          dispatch(getFaq());
        });
      })
      .catch((error) => {
        swal("Error", error, "error");
      });
  };

  const handleUpdateQuestion = (index) => {
    const question = watch(`question_${index}`);
    const answer = watch(`answer_${index}`);

    const updatedQuestions = [...prev_question];
    updatedQuestions[index] = {
      ...updatedQuestions[index],
      question,
      answer,
    };

    dispatch(postFaq({ data: { content: JSON.stringify(updatedQuestions) } }))
      .then(() => {
        swal("Success", "Question Updated Successfully", "success").then(() => {
          dispatch(getFaq());
          setSelectedQuestionIndex(null);
        });
      })
      .catch((error) => {
        swal("Error", error, "error");
      });
  };

  return (
    <Form onSubmit={handleSubmit(onSubmit)}>
      <Card title={"Frequently Asked Questions"}>
        <Row style={{ paddingTop: "10px", paddingBottom: "10px" }}>
          <Col lg={6} md={6}>
            <span
              style={{
                margin: "8px",
                display: "flex",
                fontSize: "15px",
                fontWeight: "bold",
              }}
            >
              Select Journey Type
            </span>
            <Form.Control
              as="select"
              name="journey_type"
              ref={register}
              id="journey_type"
              onChange={(e) => setJourney(e.target.value)}
            >
              <option value="">Select</option>
              {journey_type.map(({ id, name, val }) => (
                <option key={id} value={val}>
                  {name}
                </option>
              ))}
            </Form.Control>
            {errors.journey_type && (
              <Error>{errors.journey_type.message}</Error>
            )}
          </Col>
          <Col lg={6} md={6}>
            <span
              className="mb-0.5rem"
              style={{
                margin: "8px",
                display: "flex",
                fontSize: "15px",
                fontWeight: "bold",
              }}
            >
              Select Question Category
            </span>
            <Form.Control
              as="select"
              name="category"
              ref={register}
              id="category"
              onChange={(e) => setCategory(e.target.value)}
            >
              <option value="">Select</option>
              {(journey === "car"
                ? categoriesOfCar
                : journey === "bike"
                ? categoriesOfBike
                : journey === "cv"
                ? categoriesOfCv
                : categoriesOfOther
              ).map(({ title, id, category }) => (
                <option key={id} value={category}>
                  {title}
                </option>
              ))}
            </Form.Control>
            {errors.category && <Error>{errors.category.message}</Error>}
          </Col>
        </Row>
        {journey?.length > 0 &&
          category?.length > 0 &&
          prev_question
            .filter(
              (question) =>
                question.category === category &&
                question.journeyType === journey
            )
            .map((question, index) => (
              <div key={index}>
                <div className="py-2">
                  <label>Question</label>
                  <Form.Control
                    autoComplete="none"
                    type="text"
                    size="sm"
                    ref={register}
                    name={`question_${index}`}
                    defaultValue={question.question}
                    readOnly={selectedQuestionIndex === index ? false : true}
                  />
                </div>
                <div className="py-2">
                  <label>Answer</label>
                  <Form.Control
                    autoComplete="none"
                    type="text"
                    size="sm"
                    ref={register}
                    as="textarea"
                    name={`answer_${index}`}
                    defaultValue={question.answer}
                    readOnly={selectedQuestionIndex === index ? false : true}
                  />
                </div>
                {selectedQuestionIndex === index ? (
                  <>
                    <StyledButton
                      type="button"
                      onClick={() => handleUpdateQuestion(index)}
                      style={{ marginRight: "15px" }}
                    >
                      Update
                    </StyledButton>
                    <StyledButton
                      type="button"
                      onClick={() => setSelectedQuestionIndex(null)}
                    >
                      Cancel
                    </StyledButton>
                  </>
                ) : (
                  <>
                    <StyledButton
                      type="button"
                      onClick={() => setSelectedQuestionIndex(index)}
                      style={{ marginRight: "15px" }}
                    >
                      Edit
                    </StyledButton>
                    <StyledButton
                      type="button"
                      onClick={() => handleDeleteQuestion(question?.questionId)}
                    >
                      Delete
                    </StyledButton>
                  </>
                )}
              </div>
            ))}

        <div className="py-2">
          <label>New Question</label>
          <Form.Control
            autoComplete="none"
            type="text"
            size="sm"
            ref={register}
            name="question"
            placeholder="Enter your question"
          />
          {errors.question && <Error>{errors.question.message}</Error>}
        </div>
        <div className="py-2">
          <label>Answer</label>
          <Form.Control
            autoComplete="none"
            type="text"
            size="sm"
            ref={register}
            as="textarea"
            name="answer"
            placeholder="Enter your answer"
          />
          {errors.answer && <Error>{errors.answer.message}</Error>}
        </div>
        <StyledButton type="submit">Submit</StyledButton>
      </Card>
    </Form>
  );
};

export default QuestionForm;

const StyledButton = styled.button`
  font-size: 18px;
  border-radius: 4px;
  background: ${({ color }) => (color ? color : "rgb(67 56 202)")};
  color: white;
  padding: 3px 10px;
  @media (max-width: 993px) {
    font-size: 12px;
  }
`;

export const Error = styled.span`
  display: block;
  margin-top: 4px;
  line-height: 17px;
  font-size: 14px;
  color: #d43d3d;
`;
