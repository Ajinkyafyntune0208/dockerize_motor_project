import styled from "styled-components";

export const TabWrapper = styled.div`
  max-height: 55px;
  min-width: 120px;
  margin: px 30px;
  @media (max-width: 767px) {
    margin: 15px -10px;
    font-size: 10px;
  }
  display: flex;
  left: 90px;

  //	justify-content: center;
  // background-color: #ffffff;
  border-radius: 1.5em;
  padding: ${({ smallTab }) => (smallTab ? "0em 0.8em" : "0.6em 0.8em")};
  //max-width: ${({ width }) => width || "250px"};
  // box-shadow: 0 10px 15px 11px rgba(0, 0, 0, 0.1),
  // 	0 4px 6px -2px rgba(0, 0, 0, 0.05);

  @media (max-width: 400px) {
    margin: 15px -10px;
    font-size: 8px;
  }
`;

export const Tab = styled.div`
  font-size: 0.87em;
  @media (max-width: 1200px) {
    font-size: 11px;
  }
  @media (max-width: 767px) {
    font-size: ${({ shareTab }) => (shareTab ? "10px" : "9px")};
  }
  @media (max-width: 400px) {
    font-size: ${({ shareTab }) => (shareTab ? "10px" : "8px")};
  }
  font-weight: 600;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Titillium Web , sans-serif"};
  letter-spacing: ${import.meta.env.VITE_BROKER === "BAJAJ" ||
  import.meta.env.VITE_BROKER === "ACE" ||
  import.meta.env.VITE_BROKER === "SRIDHAR"
    ? "0"
    : "1px"};
  text-align: center;
  cursor: ${({ disable }) => (disable ? "not-allowed" : "pointer")};
  user-select: none;
  border: 1px solid blue;
  //border-radius: 1.5em;
  transition: all 0.5s;
  padding: ${({ isActive }) => (isActive ? ".5em 1em" : ".5em 1em")};
  color: ${({ isActive, color, theme, disable }) =>
    disable
      ? "#d3d3d3"
      : isActive
      ? theme?.FilterConatiner?.clearAllTextColor
        ? theme?.FilterConatiner?.clearAllTextColor
        : "black"
      : color || theme.FilterConatiner?.lightColor || "#858585"};
  background-color: ${({ isActive, color, theme }) =>
    isActive
      ? color || theme.FilterConatiner?.lightColor || "#f3ff91"
      : "#ffff"};
  ${({ isActive, color, theme, disable }) =>
    !isActive && !disable
      ? `&:hover{
     background-color : ${
       color || theme.FilterConatiner?.lightColor || "#f3ff91"
     };
     color : #ffffff;
  }`
      : ""}

  border: ${({ isActive, border, theme, disable }) =>
    disable
      ? ".5px solid #d3d3d3"
      : isActive
      ? theme?.FilterConatiner?.lightBorder || ".5px solid #eaff4d"
      : theme?.FilterConatiner?.lightBorder || ".5px solid #eaff4d"}
`;
