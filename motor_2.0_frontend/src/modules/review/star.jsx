import React from "react";
import styled from "styled-components";
import one from "../../assets/img/1.png";
import two from "../../assets/img/2.png";
import three from "../../assets/img/3.png";
import four from "../../assets/img/4.png";
import five from "../../assets/img/5.png";
import _ from "lodash";

const CustomStarRating = ({ value, onChange, ratingText }) => {
  const handleStarClick = (newValue) => {
    if (onChange) {
      onChange(newValue);
    }
  };

  const starImages = [five, four, three, two, one];

  const reverseRatingText = [...ratingText].reverse();

  return (
    <StarContainer>
      {[5, 4, 3, 2, 1].map((index) => (
        <Container onClick={() => handleStarClick(index)} key={index}>
          <Star
            selected={value === index}
            key={index}
            src={starImages[index - 1]}
            alt={`star-${index}`}
          />
          <Message index={index}>
            {index === value
              ? !_.isEmpty(reverseRatingText)
                ? reverseRatingText[5 - index]
                : ""
              : ""}
          </Message>
        </Container>
      ))}
    </StarContainer>
  );
};

export default CustomStarRating;

const StarContainer = styled.div`
  display: flex;
  justify-content: space-around;
  align-items: center;
  width: 100%;
  margin: 25px 0;
`;

const Star = styled.img`
  width: 66px;
  height: auto;
  cursor: pointer;
  margin-bottom: 5px;
  @media (max-width: 767px) {
    width: 45px;
  }
`;

const Message = styled.p`
  background: ${({ index }) =>
    index === 1
      ? "#fffaf0"
      : index === 2
      ? "#fff3da"
      : index === 3
      ? "#ffefd3"
      : index === 4
      ? "#e9ffe9"
      : "#e5ffe5"};
  color: ${({ theme, index }) =>
    index === 1
      ? "red"
      : index === 2
      ? "#FFA500"
      : index === 3
      ? "#5f5f00"
      : index === 4
      ? "lightgreen"
      : "green"};
  border-radius: 5px;
  text-transform: capitalize;
`;

const Container = styled.div`
  cursor: pointer;
  width: 100px;
  height: 100px;
  @media (max-width: 767px) {
    width: 50px;
    height: 50px;
  }
`;
