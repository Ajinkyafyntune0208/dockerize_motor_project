import React, { useEffect } from "react";
import styled from "styled-components";
import ReviewImage from "../../assets/img/review.jpeg";
import { Controller, useForm } from "react-hook-form";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";
import { ErrorMsg } from "components";
import { useDispatch, useSelector } from "react-redux";
import { postFeedback } from "modules/Home/home.slice";
import { useLocation } from "react-router-dom";
import swal from "sweetalert";
import CustomStarRating from "./star";

const yupValidate = yup.object({
  rating: yup.number().required("Rating is required"),
});

const ReviewForm = () => {
  const { feedback, error, theme_conf } = useSelector((state) => state.home);

  const { register, handleSubmit, errors, control, setValue, reset } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "onBlur",
    reValidateMode: "onBlur",
  });

  if (error) {
    swal({
      title: "Error",
      text: error,
      icon: "error",
    });
  }

  useEffect(() => {
    if (feedback?.comments) {
      setValue("reviewText", feedback?.comments);
    }
    if (feedback?.overall_experience) {
      setValue("rating", feedback?.overall_experience);
    }
  }, [feedback, setValue]);

  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");

  const dispatch = useDispatch();

  // work as a get api
  useEffect(() => {
    dispatch(
      postFeedback({
        userProductJourneyId: enquiry_id,
      })
    );
  }, [dispatch, enquiry_id]);

  const onSubmit = (data) => {
    const reviewData = {
      userProductJourneyId: enquiry_id,
      overallExperience: data?.rating,
      comments: data?.reviewText,
    };
    dispatch(postFeedback(reviewData));

    swal({
      title: "Success",
      text: "Feedback submitted successfully",
      icon: "success",
      timer: 2000,
      buttons: false,
    }).then(() => {
      window.location.href = `/payment-success?enquiry_id=${enquiry_id}&feedback=1`;
    });
  };

  return (
    <CenteredContainer>
      <ReviewFormContainer>
        <LeftDiv>
          <Heading1>
            We Are Excited To Know Your <br /> Feedback !
          </Heading1>
          <Image src={ReviewImage} alt="reviewImage" />
        </LeftDiv>
        <Form onSubmit={handleSubmit(onSubmit)}>
          <Heading>Please Rate Your Experience</Heading>
          <RatingContainer>
            <Controller
              control={control}
              name="rating"
              render={({ onChange, value, name }) => (
                <CustomStarRating
                  value={value}
                  onChange={onChange}
                  ref={register}
                  name={name}
                  ratingText={theme_conf?.feedback?.labels}
                />
              )}
            />
          </RatingContainer>

          <ReviewTextArea
            placeholder="Write your review here..."
            name="reviewText"
            rows="6"
            ref={register}
          />
          {errors && (
            <ErrorMsg className="text-left">{errors?.rating?.message}</ErrorMsg>
          )}
          <ButtonsContainer>
            <SubmitButton type="submit">Submit Feedback</SubmitButton>
          </ButtonsContainer>
        </Form>
      </ReviewFormContainer>
    </CenteredContainer>
  );
};

export default ReviewForm;

const CenteredContainer = styled.div`
  background: rgba(252, 239, 239, 1);
  height: 100vh;
  @media (max-width: 767px) {
    min-height: auto;
    margin-top: 25px;
  }
`;

const ReviewFormContainer = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 40px;
  border-radius: 8px;
  text-align: center;
  gap: 50px;
  width: 90%;
  margin: auto;
  @media (max-width: 767px) {
    flex-direction: column;
    width: 100% !important;
    padding: 20px;
  }
`;

const Image = styled.img`
  width: 100%;
  flex: 1;
  mix-blend-mode: darken;
`;

const LeftDiv = styled.div`
  @media (max-width: 767px) {
    display: none;
  }
`;

const Form = styled.form`
  width: 100% !important;
  box-shadow: rgba(0, 0, 0, 0.16) 0px 3px 6px, rgba(0, 0, 0, 0.23) 0px 3px 6px;
  box-shadow: 0px 4px 4px 0px rgba(237, 28, 36, 0.4);
  background: #ffffff;
  padding: 40px 32px;
  border-radius: 15px;
  z-index: 1;
  @media (max-width: 767px) {
    padding: 40px 15px;
  }
`;

const Heading1 = styled.h2`
  font-size: 36px;
  font-weight: 600;
  line-height: 48px;
  letter-spacing: 0em;
  text-align: left;
  color: ${({ theme }) =>
    theme.QuoteCard?.color ? `${theme.QuoteCard?.color}` : "#bdd400 "};
  flex: 2;
`;

const Heading = styled.h2`
  color: #333;
  font-size: 24px;
  text-align: center;
`;

const RatingContainer = styled.div`
  display: flex;
  align-items: center;
  gap: 20px;
  margin: 20px 0;
`;

const ReviewTextArea = styled.textarea`
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
  resize: vertical;
  margin: 10px 0;
  border-radius: 12px 12px 0px 0px;
  box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 4px;
  outline: none;
  &::placeholder {
    text-align: center;
    padding-top: 10%;
    vertical-align: middle;
  }
`;

const ButtonsContainer = styled.div`
  display: flex;
  justify-content: space-between;
  margin: 20px 0;
`;

const SubmitButton = styled.button`
  background: ${({ theme }) =>
    theme.QuoteCard?.color ? `${theme.QuoteCard?.color}` : "#bdd400 "};
  color: #fff;
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
  flex: 1;
  margin-top: -44px;
  border-radius: 0px 0px 12px 12px;
`;
