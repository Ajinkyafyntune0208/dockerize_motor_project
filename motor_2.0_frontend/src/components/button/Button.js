import styled from "styled-components";

export const Button = styled.button`
  padding: 11px 17px;
  font-size: 13px;
  font-weight: bold;
  letter-spacing: 0;
  outline: none;
  &:not(:disabled):not(.disabled) {
    cursor: pointer;
  }
  &:not(:disabled):not(.disabled).active,
  &:not(:disabled):not(.disabled):active {
    background-image: none;
    color: #20d2a3;
  }

  &:focus {
    outline: none;
  }
  white-space: nowrap;
  &:hover {
    box-shadow: ${({ theme }) =>
      theme.dark
        ? "0 10px 15px 6px rgb(0 0 0 / 10%), 0 4px 6px -2px rgb(0 0 0 / 5%)"
        : "0px 7px 15px -3px #b6b6b6, 0 4px 6px -2px #F9FBE7"};
  }
  ${({
    buttonStyle,
    hex1,
    hex2,
    theme,
    borderRadius,
    shadow,
    height,
    color,
  }) => {
    switch (buttonStyle) {
      case "danger":
        return `
        background: ${theme.Button?.danger?.background || "#ff8983"};
        color: ${theme.Button?.danger?.text_color || "#fff"};
        font-weight: 600;
        letter-spacing: 1px;
        border-radius: 50px;
        padding: 11px 30px;
        border: 1px dotted ${theme.Button?.danger?.border_color || "#ff8683"};
        margin-right: 25px;
      `;
      case "warning":
        return `
        background:${theme.Button?.warning?.background || "#eebb4d"} ;
        color:${theme.Button?.warning?.text_color || "#fff"} ;
        font-weight: 600;
        letter-spacing: 1px;
        border-radius: 50px;
        padding: 11px 30px;
        border: 1px dotted ${theme.Button?.warning?.border_color || "#eebb4d"};
        margin-right: 25px;
      `;
      case "outline":
        return `
        border: 1px dashed ${theme.Button?.outline?.border_color || "#cb68d9"};
        border-radius: 50px;
        background: ${theme.Button?.outline?.background || "#fff"};
        color:${theme.Button?.outline?.text_color || "#b406cc"} ;
        font-weight: 600;
        letter-spacing: 1px;
        font-size: 12px;
      `;

      case "square-outline":
        return `
        border: 1px dashed ${
          theme.Button?.square_outline?.border_color || "#CE93D8"
        };
        border-radius: 3px;
        background: ${theme.Button?.square_outline?.background || "#fff"};
        color:${theme.Button?.square_outline?.text_color || "#000000"} ;
        font-weight: 600;
        letter-spacing: 1px;
        font-size: 12px;
      `;

      case "outline-secondary":
        return `
        border-radius: 100px;
        color:${theme.Button?.outline_secondary?.text_color || "#606060"} ;
        letter-spacing: 1px;
        font-size: 14px;
        border: 1px dotted ${
          theme.Button?.outline_secondary?.border_color || "#606060"
        };
        padding: 10px 24px !important;
        background:${theme.Button?.outline_secondary?.background || "#efefef"};
        /* margin: 0px 10px; */
      `;
      case "submit-disabled":
        return `
        border-radius: 100px 10px 100px 100px;
        background: ${theme.Button?.submit_disabled?.background || "#efefef"};
        color:${theme.Button?.submit_disabled?.text_color || "#606060"} ;
        letter-spacing: 1px;
        font-size: 14px;
        border: 1px dotted ${
          theme.Button?.submit_disabled?.border_color || "#606060"
        };
        padding: 10px 40px;
        box-shadow: 0 0 black;
      `;
      case "outline-solid":
        return `
        border-radius: ${borderRadius || "100px"};
        background: linear-gradient(69.83deg, ${
          hex1 || theme.Button?.outline_solid?.background1 || "#0084f4"
        } 0%, ${
          hex2 || theme.Button?.outline_solid?.background2 || "#00c48c"
        } 100%);
        color:${color || theme.Button?.outline_solid?.text_color || "white"} ;
        letter-spacing: 1px;
        font-size: 14px;
        border: 1px solid ${
          theme.Button?.outline_solid?.border_color || hex1 || "#4ca729"
        };
        padding: 10px 40px;
          box-shadow: ${
            shadow || "0px 7px 15px -3px #b6b6b6, 0 4px 6px -2px #F9FBE7"
          };
        &:not(:disabled):not(.disabled).active,
        &:not(:disabled):not(.disabled):active {
          outline: none !important;
          box-shadow: none;
          background-image: none;
          color: ${theme.Payment?.color3 || hex1};
        }
        height: ${height || "auto"};
        &:active {
          background: ${theme.Payment?.color2 || ""};
        }
      `;

      default:
        return `
        border-radius: 100px 10px 100px 100px;
        background: ${theme.Button?.default?.background || "#3fd49f"};
        color: ${theme.Button?.default?.text_color || "#fff"};
        letter-spacing: 1px;
        font-size: 14px;
        border: 1px dotted ${theme.Button?.default?.border_color || "#1ad2a4"};
        padding: 10px 40px;
        
      `;
    }
  }}
`;

export default Button;
