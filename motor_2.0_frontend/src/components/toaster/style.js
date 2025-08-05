import styled, { createGlobalStyle } from "styled-components";

export const GlobalStyle = createGlobalStyle`
body {
	.Toastify__progress-bar-theme--light {
		background: ${({ Theme }) =>
      `${
        Theme?.QuoteCard?.color
          ? `${Theme?.QuoteCard?.color} !important`
          : "#bdd400 !important"
      }`};
	}
}
`;

export const ToastMessageContainer = styled.div`
  width: 100%;
  font-family: ${({ Theme }) =>
    Theme?.regularFont?.fontFamily || " Inter-Medium"};
  display: flex;
  justify-content: center;
  align-items: center;
  flex-direction: column;

  .toasterStyle {
    .Toastify__progress-bar-theme--light {
      background: ${({ Theme }) =>
        `${
          Theme.QuoteCard?.color
            ? `${Theme.QuoteCard?.color} !important`
            : "#bdd400 !important"
        }`};
    }
  }
`;

export const ToastButton = styled.div`
  font-family: ${({ Theme }) =>
    Theme?.regularFont?.fontFamily || " Inter-Medium"};
  background-color: ${({ Theme }) => Theme.QuotePopups?.color || "#bdd400"};
  border: ${({ Theme }) => Theme.QuotePopups?.border || "1px solid #bdd400"};
  display: flex;
  justify-content: center;
  align-items: center;
  color: ${({ Theme }) => Theme.leadPageBtn?.textColor || "#000"};
  flex-direction: column;
  padding: 5px 10px;
  margin-top: 5px;
  border-radius: ${({ Theme }) =>
    Theme.QuoteBorderAndFont?.borderRadius || "30px"};
  .btnText {
    background-color: ${({ Theme }) => Theme.QuotePopups?.color || "#bdd400"};
  }
`;

export const ToastMessage = styled.div`
  font-family: ${({ Theme }) =>
    Theme?.regularFont?.fontFamily || " Inter-Regular"};
  color: black;
  font-size: 14px;
`;

export const ButtonContainer = styled.div`
  display: flex;
  align-items: center;
  width: 100%;
  justify-content: space-evenly;
`;
