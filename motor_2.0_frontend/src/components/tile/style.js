import styled from "styled-components";

export const TileWrap = styled.div`
  width: 165px;
  /* height: 54px; */
  font-family: ${({ theme }) =>
    theme.regularFont?.fontFamily || " Inter-Medium"};
  border-radius: 2px;
  margin-bottom: 8px;
`;

export const previousInsurerSelected = styled.div`
  width: 165px;
  font-family: ${({ theme }) =>
    theme.regularFont?.fontFamily || " Inter-Medium"};
  border-radius: 2px;
  margin-bottom: 8px;
  border: 2px solid #006600;
  display: flex;
  align-items: center;
  justify-content: center;
`;

export const Label = styled.div`
  width: ${(props) =>
    props.width ? props.width : props.flatTile ? "100%" : "148px"};
  height: ${(props) => (props.height ? props.height : "78px")};
  display: ${(props) => (props.flatTile ? "flex" : "table-cell !important")};
  vertical-align: middle;
  background-color: ${({ fuelType, theme }) =>
    fuelType ? theme.links?.color : "#fff"};
  color: ${({ fuelType, theme }) => (fuelType ? "#fff" : "#546e7a")};
  font-size: ${(props) => (props.fontSize ? props.fontSize : "14px")};
  font-weight: ${(props) => (props.fontWeight ? props.fontWeight : "500")};
  letter-spacing: 0.5px;
  line-height: 17px;
  text-align: center;
  padding: 0 2px;
  margin-bottom: 2px;
  box-shadow: ${({ shadow, border }) =>
    border ? "none" : shadow ? shadow : "0px 0px 7px 0px rgba(0, 0, 0, 0.64)"};
  -webkit-box-shadow: ${({ shadow, border }) =>
    border ? "none" : shadow ? shadow : "0px 0px 7px 0px rgba(0, 0, 0, 0.64)"};
  -moz-box-shadow: ${({ shadow, border }) =>
    border ? "none" : shadow ? shadow : "0px 0px 7px 0px rgba(0, 0, 0, 0.64)"};
  transition: all 0.2s ease-in-out;
  /* border: 1px solid #e3e4e8; */
  border-radius: 10px;
  transition: all 0.1s ease-in-out;
  border: ${({ border }) => border || "1px solid #e3e4e8"};
  &:hover,
  &:focus {
    box-shadow: ${({ theme }) =>
      theme?.Tile?.boxShadow
        ? theme?.Tile?.boxShadow
        : "0px 0px 7px 0px #33cc33"};
    -webkit-box-shadow: ${({ theme }) =>
      theme?.Tile?.boxShadow
        ? theme?.Tile?.boxShadow
        : "0px 0px 7px 0px #33cc33"};
    -moz-box-shadow: ${({ theme }) =>
      theme?.Tile?.boxShadow
        ? theme?.Tile?.boxShadow
        : "0px 0px 7px 0px #33cc33"};
    transition: all 0.1s ease-in-out;
  }
  &:hover {
    transform: ${({ flatTile, border }) =>
      border ? "scale(1.01)" : flatTile ? "" : "scale(1.1)"};
    color: ${({ theme, fuelType }) =>
      fuelType
        ? "#fff"
        : theme?.Tile?.color
        ? theme?.Tile?.color
        : "#006f00 !important"};
    font-weight: 500;
  }
  &:hover {
    border: 2px solid transparent;
    ${import.meta.env.VITE_BROKER === "TATA" &&
    `background: linear-gradient(white, white) padding-box,
    linear-gradient(90deg, rgba(0,153,242,0.5) 18%, rgba(75,106,248,0.5) 50%, rgba(154,57,254,0.5) 82%)
        border-box;
    border: 2px solid transparent;`}
  }
  cursor: pointer;
`;

export const Img = styled.img`
  width: ${(props) =>
    props.ImgWidth ? props.ImgWidth : props.prevIns ? "100%" : "54%"};
  margin: ${(props) =>
    props?.marginImg
      ? props?.marginImg
      : props?.flatTile
      ? "auto 2.5px auto 20px"
      : "auto"};
  display: block;
  vertical-align: middle;
  height: ${(props) =>
    props.Imgheight
      ? props.Imgheight
      : props.prevIns
      ? "47px"
      : props?.flatTile
      ? ""
      : "45px"};
  ${({ flatTile, flatTilexs }) =>
    flatTilexs ? `padding: 2px` : flatTile ? `padding: 3px` : ``};
  ${({ objectFit }) => (objectFit ? `object-fit: contain` : ``)};
`;

export const StyledDiv = styled.div`
  .Selected {
    // font-weight: 800;
    font-size: ${(props) => (props.fontSize ? props.fontSize : "14px")};
    border: ${({ theme }) => theme?.Tile?.border || "2px solid #006600"};
    color: ${({ theme, fuelType }) =>
      fuelType ? "#fff" : theme?.Tile?.color || "#006f00 !important"};
    box-shadow: ${({ theme }) =>
      theme?.Tile?.boxShadow
        ? theme?.Tile?.boxShadow
        : "0px 0px 7px 0px #33cc33  !important"};
    ${import.meta.env.VITE_BROKER === "TATA"
      ? `background: linear-gradient(white, white) padding-box,
      linear-gradient(
          90deg,
          rgba(0, 153, 242, 1) 18%,
          rgba(75, 106, 248, 1) 50%,
          rgba(154, 57, 254, 1) 82%
        )
        border-box;
    border-color: transparent;`
      : `background-color: ${({ fuelType, theme }) =>
          fuelType ? theme.links?.color : "#fff"};`}
  }
`;
