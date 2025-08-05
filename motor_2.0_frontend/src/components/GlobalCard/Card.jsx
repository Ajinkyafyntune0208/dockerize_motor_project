import React from "react";
import {
  Row1,
  Container2,
  Header,
  BottomHeader,
  Container3
} from "./style";

const Card = ({ children, title, round = false, className, style, clickHandler = () => { },  headerStyle = {}, }) => {
  const styles = (round ? { borderBottomRightRadius: "35px" } : {})
  return (
    <Row1 style={{ ...styles, ...style }} className={className} onClick={() => clickHandler()}>
      <Container2>
        <Header style={headerStyle}>
          <span className="icon"></span>
          {title}
          <BottomHeader></BottomHeader>
        </Header>
      </Container2>
      <Container3>
        {children}
      </Container3>
    </Row1 >
  )
}

export default Card;
