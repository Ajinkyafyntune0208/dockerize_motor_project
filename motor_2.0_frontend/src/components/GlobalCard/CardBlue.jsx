import React from "react";
import {
  CardRow,
  HeaderContainer,
  HeaderBlue,
  BottomHeader,
  CardContentBox
} from "./style";

const CardBlue = ({ children, title, round = false, styles, headerStyle = {}, marginTop, clickHandler = () => { } }) => {
  const style = (round ? { borderBottomRightRadius: "35px" } : {})
  return (
    <CardRow style={{ ...style, ...styles }} onClick={() => clickHandler()}>
      <HeaderContainer>
        <HeaderBlue style={headerStyle}>
          <span className="iconBlue">
          </span>
          {title}
          <BottomHeader></BottomHeader>
        </HeaderBlue>
      </HeaderContainer>
      <CardContentBox marginTop={marginTop}>
        {children}
      </CardContentBox>
    </CardRow>
  )
}

export default CardBlue;
