import styled from "styled-components";

export const QRContainer = styled.div`
  position: fixed;
  cursor: pointer;
  right: 15px;
  bottom: ${({ location }) => (location ? "34%" : "24.9%")};
  display: ${({ showQR }) => (showQR ? "block" : "none")};
  padding: 0.5rem;
  border: 2px solid rgb(37, 211, 102);
  border-radius: 12px;
  background-color: white;
  text-align: center;
  z-index: 99999999 !important;
  width: 160px;
  float: inline-end;
  h6 {
    font-weight: bolder;
    font-size: 12.64px;
    color: #2112529;
  }
  img {
    width: 140px;
    aspect-ratio: 1 / 1;
    margin-block: 0.25rem;
  }
  p {
    margin: 0;
  }
  &:hover {
    display: block;
  }
`;

export const StyledWhatsapp = styled.div`
  position: fixed;
  top: ${({
    loc,
    lessthan993,
    lessthan380,
    includeRouteShare,
    quotes,
    location,
  }) =>
    loc[2] === "compare-quote" && lessthan380
      ? "57%" //compare-page
      : lessthan380 && loc[2] === "proposal-page"
      ? "66%" //proposal-page
      : loc[2] === "quotes" && lessthan380
      ? "58%" // quote-page
      : includeRouteShare.includes(location.pathname) && quotes
      ? lessthan993 && loc[2] === "compare-quote"
        ? "59%" //compare-page
        : lessthan993
        ? "67%"
        : "64.5%" // quote page
      : "75%"};
  right: 1%;
  z-index: 9;
`;

export const StyledDiv = styled.div`
  @-webkit-keyframes fadeIn {
    0% {
      opacity: 0;
    }
    100% {
      opacity: 1;
    }
  }

  @-moz-keyframes fadeIn {
    0% {
      opacity: 0;
    }
    100% {
      opacity: 1;
    }
  }

  @-o-keyframes fadeIn {
    0% {
      opacity: 0;
    }
    100% {
      opacity: 1;
    }
  }

  @keyframes fadeIn {
    0% {
      opacity: 0;
    }
    100% {
      opacity: 1;
    }
  }

  #social-share {
    position: fixed;
    right: 0px;
    top: 85%;
    z-index: 10;
  }

  #social-share a {
    text-decoration: none;
    float: right;
  }

  #social-share a:not(:first-child) {
    animation: fadeOut 0.5s;
    display: none;
  }

  #social-share:hover a:not(:first-child) {
    display: inline;
    -webkit-animation: fadeIn 0.5s;
    -moz-animation: fadeIn 0.5s;
    -o-animation: fadeIn 0.5s;
    animation: fadeIn 0.5s;
  }

  #social-share:hover .fa-share-alt {
    border-radius: 0px;
  }
  #social-share:hover .fa-phone {
    border-radius: 0px;
  }

  #social-share a .my-social {
    border-right: 0;
  }

  #social-share a .fa-phone {
    color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "TATA"
        ? "#0099f2"
        : theme.floatButton?.floatColor || "#bdd400"};
    font-size: 21px;
  }

  #social-share a .fa-bars {
    color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "TATA"
        ? "#0099f2"
        : theme.floatButton?.floatColor || "#bdd400"};
  }

  #social-share > a > .fa-share-alt {
    color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "TATA"
        ? "#0099f2"
        : theme.floatButton?.floatColor || "#bdd400"};
    border-right: 0;
    padding: 8px 10px;
    border-radius: 3px 0px 0px 3px;
  }

  #social-share #whatsapp:focus > .fa-share-alt,
  #social-share .fa-share-alt:hover {
    background-color: ${({ theme }) =>
      theme.floatButton?.floatColor || "#bdd400"};
    border-collapse: #c7222a;
    color: #fff;
  }

  #social-share #linkedin:focus > .fa-share-alt,
  #social-share .fa-share-alt:hover {
    background-color: ${({ theme }) =>
      theme.floatButton?.floatColor || "#bdd400"};
    border-collapse: ${({ theme }) =>
      theme.floatButton?.floatColor || "#bdd400"};
    color: #fff;
  }

  #social-share #reddit:focus > .fa-share-alt,
  #social-share .fa-share-alt:hover {
    background-color: ${({ theme }) =>
      theme.floatButton?.floatColor || "#bdd400"};
    border-collapse: ${({ theme }) =>
      theme.floatButton?.floatColor || "#bdd400"};
    color: #fff;
  }

  #social-share #share:focus > .fa-bars,
  #social-share .fa-bars:hover {
    color: #fff;
    background-color: ${({ theme }) =>
      theme.floatButton?.floatColor || "#bdd400"};
    border-color: ${({ theme }) => theme.floatButton?.floatColor || "#bdd400"};
  }

  #social-share #linkedin:focus > .fa-phone,
  #social-share .fa-phone:hover {
    color: #fff;
    background-color: ${({ theme }) =>
      theme.floatButton?.floatColor || "#bdd400"};
    border-color: ${({ theme }) => theme.floatButton?.floatColor || "#bdd400"};
  }

  .my-social {
    font-size: 21px;
    padding: 10px;
    cursor: pointer;
    transition: all 0.4s ease-out;
    -webkit-transition: all 0.4s ease-out;
    -moz-o-trasition: all 0.4s ease-out;
    -o-trasition: all 0.4s ease-out;
  }
  .fa-phone {
    font-size: 21px;
  }
  .outlinemd {
    margin: 8px 10px;
    font-size: 1.5em;
    cursor: pointer;
    transition: all 0.4s ease-out;
    -webkit-transition: all 0.4s ease-out;
    -moz-o-trasition: all 0.4s ease-out;
    -o-trasition: all 0.4s ease-out;
  }
  .floatBtn {
    background: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "ONECLICK"
        ? theme.primaryColor?.color
        : "none"};
    color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "ONECLICK"
        ? "#fff"
        : import.meta.env.VITE_BROKER === "TATA"
        ? "#0099f2"
        : theme.floatButton?.floatColor || "#bdd400"};
    border: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "ONECLICK"
        ? "none"
        :import.meta.env.VITE_BROKER === "TATA"
        ? "1px solid #0099f2"
        : theme.floatButton?.floatBorder || "1px solid #bdd400"};
    border-radius: 50%;
    padding: 7px;
  }
  .floatBtn:hover {
    background: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "ONECLICK"
        ? theme.primaryColor?.color
        : import.meta.env.VITE_BROKER === "TATA"
        ? "#0099f2"
        : theme.floatButton?.floatColor || "#bdd400"};
    color: #fff;
  }
`;
