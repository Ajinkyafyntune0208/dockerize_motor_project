import React from "react";
import {
  IconlessCardRow,
  HeaderContainer,
  Header,
  BottomHeader,
  CardContentBox,
  SpanTag,
} from "./style";

const IconlessCard = ({
  children,
  title,
  round = false,
  styles,
  removeBottomHeader = false,
  headerStyle = {},
  marginTop,
  image = "",
  imageStyle = {},
  color,
  fontweight,
  padding,
  imageTagStyle = {},
  id,
  noShadow = false,
  backgroundColor,
  borderRadius,
  paddingTop,
  paddingBottom,
}) => {
  const style = round ? { borderBottomRightRadius: "0" } : {};

  return (
    <IconlessCardRow
      style={{ style, ...styles }}
      padding={padding}
      id={id}
      noShadow
    >
      <HeaderContainer
        color={color || "black"}
        fontweight={fontweight}
        backgroundColor={backgroundColor}
        borderRadius={borderRadius}
        paddingTop={paddingTop}
        paddingBottom={paddingBottom}
      >
        <Header style={headerStyle}>
          {!!image && (
            <SpanTag style={imageStyle}>
              <img
                src={image}
                style={imageTagStyle}
                alt={"N/A"}
                width={77}
                height={77}
              />
            </SpanTag>
          )}
          {title}
          {removeBottomHeader ? "" : <BottomHeader></BottomHeader>}
        </Header>
      </HeaderContainer>
      <CardContentBox marginTop={marginTop}>{children}</CardContentBox>
    </IconlessCardRow>
  );
};

export default IconlessCard;
