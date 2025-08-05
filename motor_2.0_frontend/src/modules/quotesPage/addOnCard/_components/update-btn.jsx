import React from "react";
import { ButtonContainer, ButtonSub } from "../style";

const UpdateButton = ({ id, onClick, style, text }) => {
  return (
    <ButtonContainer style={style}>
      <ButtonSub id={id} onClick={onClick}>
        {text ? text : "Update"}
      </ButtonSub>
    </ButtonContainer>
  );
};

export default UpdateButton;
