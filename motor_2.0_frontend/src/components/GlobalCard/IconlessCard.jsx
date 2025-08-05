import React from "react";
import {
  IconlessCardRow,
  HeaderContainer,
  Header,
  BottomHeader,
  CardContentBox,
} from "./style";

const IconlessCard = ({ children, title, round = false, styles, removeBottomHeader = false, headerStyle = {}, marginTop, image = "", imageStyle = {} }) => {
  const style = round ? { borderBottomRightRadius: "35px" } : {};

  return (
    <IconlessCardRow style={{ style, ...styles }}>

      <HeaderContainer>
        <Header style={headerStyle}>
          {!!image && <span style={imageStyle}><img src={image} alt={'N/A'} width={40} /></span>}
          {title}
          {removeBottomHeader ? "" : <BottomHeader></BottomHeader>}
        </Header>
      </HeaderContainer>
      <CardContentBox marginTop={marginTop}>{children}</CardContentBox>
    </IconlessCardRow>
  );
};

export default IconlessCard;
