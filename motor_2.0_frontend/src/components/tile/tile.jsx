import React, { useState } from "react";
import PropTypes from "prop-types";
import { Label, TileWrap, Img, StyledDiv } from "./style";

const Tile = ({
  text,
  logo,
  handleChange,
  id,
  value,
  width,
  height,
  name,
  register,
  imgMargin,
  setValue,
  Selected,
  Imgheight,
  onClick,
  prevIns,
  marginImg,
  lessthan600,
  fontSize,
  padding,
  ImgWidth,
  fontWeight,
  lessthan360,
  flatTile,
  flatTilexs,
  shadow,
  objectFit,
  fuelType,
  border
}) => {
  // on change method
  const handleClick = () => {
    if (setValue) {
      setValue(name, value);
    }
    if (onClick) {
      onClick();
    }
  };

  const _renderInput = () => (
    <StyledDiv
      fontSize={fontSize}
      className="w-100 mx-auto d-flex justify-content-center"
      fuelType={fuelType}
    >
      <TileWrap flatTile={flatTile} className={`w-100 mx-auto my-2`}>
        <>
          <input type="hidden" name={name} ref={register} />
          <Label
            flatTile={flatTile}
            lessthan600={lessthan600}
            fontSize={fontSize}
            fontWeight={fontWeight}
            shadow={shadow}
            width={width}
            height={height}
            onClick={handleClick}
            border={border}
            className={
              Selected &&
              (Number(value) === Number(Selected) || value === Selected)
                ? "Selected"
                : ""
            }
            fuelType={fuelType}
          >
            {logo && !lessthan360 && (
              <Img
                flatTilexs={flatTilexs}
                flatTile={flatTile}
                lessthan600={lessthan600}
                prevIns={prevIns}
                src={logo}
                Imgheight={Imgheight}
                ImgWidth={ImgWidth}
                style={imgMargin && { marginBottom: imgMargin }}
                marginImg={marginImg}
                lessthan360={lessthan360}
                objectFit={objectFit}
                alt="brand logo"
              />
            )}
            {flatTile ? (
              <text style={{ margin: logo ? "auto 2.5px auto 27px" : "auto" }}>
                {text}
              </text>
            ) : (
              text
            )}
          </Label>
        </>
      </TileWrap>
    </StyledDiv>
  );

  return <div className="form-group-input">{_renderInput()}</div>;
};

export default Tile;
