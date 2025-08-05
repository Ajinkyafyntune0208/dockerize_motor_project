import React from "react";
import ReactTooltip from "react-tooltip";
import "./CustomTooltip.scss";
import styled from "styled-components";
import { useMediaPredicate } from "react-media-hook";
function CustomTooltip({ ...props }) {
  const moreThan993 = useMediaPredicate("(min-width: 993px)");
  const {
    id,
    place,
    customClassName,
    Position,
    allowClick,
    arrowColor,
    noDisplay,
    small,
    mmvText,
  } = props;
  let offset = undefined;
  let mobilePlace = undefined;
  if (window.matchMedia("(max-width: 767px)").matches) {
    mobilePlace = "top";
  }

  return (
    <>
      {props.children}

      {!noDisplay && (
        <TooltipContainer small={small} mmvText={mmvText}>
          <ReactTooltip
            id={(id && moreThan993) || allowClick ? id : undefined}
            className={`customTooltip ${customClassName}  modifyTipStyle`}
            offset={
              mobilePlace === "top" ? undefined : Position ? Position : offset
            }
            type="light"
            effect={"solid"}
            place={mobilePlace === "top" ? undefined : place}
            // backgroundColor={backColor ? backColor : "rgba(37,56,88)"}
            borderColor="#000"
            arrowColor={arrowColor ? "transparent" : ""}
          />
        </TooltipContainer>
      )}
    </>
  );
}

export default CustomTooltip;

export const TooltipContainer = styled.span`
  .customTooltip {
    width: ${({ small, mmvText }) =>
      small ? "175px" : mmvText ? "300px" : "275px"};
    font-family: ${({ theme }) => theme?.fontFamily};
  }
`;
