import React, { useRef } from "react";
import styled, { keyframes } from "styled-components";
import PropTypes from "prop-types";
import { useMediaPredicate } from "react-media-hook";
import { setShowPop } from "modules/quotesPage/quote.slice";
import { useDispatch } from "react-redux";
import { useOutsideClick } from "hoc";

const Popup = ({
  show,
  onClose,
  content,
  height,
  width,
  position,
  top,
  left,
  backGround,
  outside,
  overFlowDisable,
  hiddenClose,
  noBlur,
  overflowX,
  backGroundImage,
  color,
  zIndexPopup,
  mobileHeight,
  marginTop,
  noClosingTag,
  svgPosition,
  reduxClose,
  animDuration,
}) => {
  const dropDownRef = useRef(null);
  const lessthan767 = useMediaPredicate("(max-width: 767px)");

  const dispatch = useDispatch();

  const closePop = () => {
    dispatch(setShowPop(false));
  };

  // useOutsideClick(dropDownRef, () =>
  //   reduxClose ? closePop() : onClose(outside)
  // );

  return (
    show && (
      <PopupC
        visible={show}
        noBlur={noBlur}
        zIndexPopup={zIndexPopup}
        backGround={backGround}
      >
        <Content
          className={lessthan767 ? "disable-scrollbars" : ""}
          lessthan767={lessthan767}
          ref={dropDownRef}
          height={height}
          width={width}
          position={position}
          maxwidth={width}
          left={left}
          backGround={backGround}
          overFlowDisable={overFlowDisable}
          noBlur={noBlur}
          overflowX={overflowX}
          backGroundImage={backGroundImage}
          mobileHeight={mobileHeight}
          marginTop={marginTop}
        >
          <CloseButton
						hiddenClose={hiddenClose}
						onClick={() => {
							onClose(false);
						}}
					>
						&times;
					</CloseButton>
          {lessthan767 && (
            <PaymentTermOverlayClose
              hiddenClose={hiddenClose}
              onClick={() => {
                if (reduxClose) {
                  closePop();
                } else {
                  onClose(false);
                }
              }}
              id="close"
            >
              <svg
                version="1.1"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                style={{ height: svgPosition ? "27px" : " 25px" }}
              >
                <path
                  fill={color ? color : "#000"}
                  d="M12,2c-5.53,0 -10,4.47 -10,10c0,5.53 4.47,10 10,10c5.53,0 10,-4.47 10,-10c0,-5.53 -4.47,-10 -10,-10Zm5,13.59l-1.41,1.41l-3.59,-3.59l-3.59,3.59l-1.41,-1.41l3.59,-3.59l-3.59,-3.59l1.41,-1.41l3.59,3.59l3.59,-3.59l1.41,1.41l-3.59,3.59l3.59,3.59Z"
                ></path>
                <path fill="none" d="M0,0h24v24h-24Z"></path>
              </svg>
            </PaymentTermOverlayClose>
          )}
          {content}
        </Content>
      </PopupC>
    )
  );
};

// PropTypes
Popup.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
  content: PropTypes.element,
  height: PropTypes.string,
  width: PropTypes.string,
  position: PropTypes.string,
  left: PropTypes.string,
  backGround: PropTypes.string,
  outside: PropTypes.bool,
  hiddenClose: PropTypes.bool,
};

// DefaultTypes
Popup.defaultProps = {
  show: false,
  onClose: () => {},
  content: null,
  height: "200px",
  width: "640px",
  position: "middle",
  outside: false,
  hiddenClose: false,
};

const moveDown = keyframes`
from{
  top:0;
  opacity:0;
}
to {
  top:${(props) => (props.position === "top" ? "20%" : "35%")};
  opacity:1;
}
`;

const moveUp = keyframes`
from{
  bottom:0;
  opacity:0;
}
to {
  top:${(props) => (props.position === "top" ? "20%" : "35%")};
  opacity:1;
}
`;

const PopupC = styled.div`
  min-height: 100vh;
  width: 100%;
  position: fixed;
  top: 0;
  left: 0;
  overflow-y: auto;
  background-color: ${({ noBlur, backGround }) =>
    backGround === "transparent"
      ? "rgba(1, 1, 1, .9)"
      : noBlur === "true"
      ? "rgba(1, 1, 1, 0)"
      : "rgba(1, 1, 1, 0.6)"};
  z-index: ${({ zIndexPopup }) =>
    zIndexPopup === true ? "100000 !important;" : "9999 !important;"};
  opacity: ${({ visible }) => (visible === true ? "1" : "0")};
  visibility: ${({ visible }) => (visible === true ? "visible" : "hidden")};
  transition: all 0.3s;

  .disable-scrollbars {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE 10+ */

    &::-webkit-scrollbar {
      background: transparent; /* Chrome/Safari/Webkit */
      width: 0px;
    }
  }
`;

const Content = styled.div`
  background: ${({ backGroundImage }) =>
    backGroundImage
      ? `	linear-gradient(to bottom, transparent, #fff),
	url(${
    import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ""
  }/assets/images/background-green5-min.jpg)`
      : "initial"};

  position: absolute;
  overflow: ${({ overFlowDisable }) =>
    overFlowDisable === true ? "none" : "auto"};
  overflow-x: ${({ overflowX }) => (overflowX === true ? "none" : "auto")};
  animation-name: ${(lessthan767) =>
    lessthan767 ? moveUp : moveDown}!important;
  animation-duration: ${(animDuration) =>
    animDuration ? "0s" : "0.5s"} !important;
  top: ${({ position }) =>
    position === "top"
      ? "20%"
      : position === "bottom"
      ? "45%"
      : position === "middle"
      ? "40%"
      : position === "responsiveTop"
      ? "235px"
      : "35%"};
  height: ${({ height }) => height};
  width: ${({ width }) => width};
  left: ${({ left }) => (left ? left : "50%")};
  transform: translate(-50%, -40%);
  background-color: ${({ backGround }) =>
    backGround === "transparent"
      ? "transparent"
      : backGround === "grey"
      ? "rgb(235, 236, 243)"
      : "#fff"};
  transition: all 0.5s;
  border-radius: ${({ noBlur }) => (noBlur === "true" ? "12px" : "6px")};
  box-shadow: ${({ backGround }) =>
    backGround === "transparent"
      ? "transparent"
      : "	0 6px 12px 0 rgb(0 0 0 / 26%)"};

  margin-top: ${({ marginTop }) => (marginTop ? marginTop : "")};
  @media (max-width: ${({ maxwidth }) => maxwidth}) {
    width: 100% ;
    height: ${({ mobileHeight }) =>
      mobileHeight ? mobileHeight : "99vh"};
  }
`;

const CloseButton = styled.a`
  display: ${({ hiddenClose }) => (hiddenClose ? "none" : "block")};
  float: right;
  font-size: 36px;
  margin-right: 10px;
  color: #000000;
  cursor: pointer;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `sans-serif`};
  color: #363636;
  text-decoration: none;
  position: relative;
  z-index: 1000;
  &:link,
  &:visited,
  &:hover {
    text-decoration: none;
    color: #363636;
  }
`;

const PaymentTermOverlayClose = styled.div`
  display: ${({ hiddenClose }) => (hiddenClose ? "none" : "flex")};
  justify-content: flex-end;
  position: absolute;
  top: 10px;
  right: 10px;
  cursor: pointer;
  z-index: 1111;
  &:hover {
    text-decoration: none;
    color: rgb(230, 0, 0);
  }
`;

export default Popup;
