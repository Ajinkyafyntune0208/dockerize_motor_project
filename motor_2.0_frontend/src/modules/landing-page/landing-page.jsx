/* eslint-disable jsx-a11y/anchor-is-valid */
import React from "react";
import "./landing-page.css";
import { reloadPage, UrlFn } from "utils";
import styled, { createGlobalStyle } from "styled-components";
import { useHistory } from "react-router";
import { useSelector } from "react-redux";
import _ from "lodash";
import { useMediaPredicate } from "react-media-hook";
import { TwoWheeler, DriveEta, LocalShipping } from "@mui/icons-material/";
import { LogoFn } from "components";

export const LandingPage = () => {
  const history = useHistory();
  const { typeAccess } = useSelector((state) => state.login);
  const AccessControl =
    !_.isEmpty(typeAccess) &&
    import.meta.env.VITE_API_BASE_URL !== "https://olaapi.fynity.in/api"
      ? _.compact(
          typeAccess.map((item) =>
            item?.product_sub_type_code
              ? item?.product_sub_type_code.toLowerCase()
              : null
          )
        )
      : [];

  //decrypt

  const LogoFunction = () => {
    if (
      import.meta.env?.VITE_BROKER === "ABIBL" ||
      import.meta.env?.VITE_BROKER === "BAJAJ"
    ) {
      return (
        <Logo
          src={LogoFn()}
          alt="logo"
          onClick={() => {
            import.meta.env?.VITE_BROKER === "BAJAJ"
              ? reloadPage("https://www.bajajcapitalinsurance.com/")
              : reloadPage("http://abibl-prod-dashboard.fynity.in/");
          }}
        />
      );
    } else {
      return <Logo src={LogoFn()} alt="logo" onClick={() => reloadPage("/")} />;
    }
  };

  const OnTypeSelect = (type) => {
    const custom =
      import.meta.env.VITE_API_BASE_URL ===
        "https://uatmotorapis.aceinsurance.com/api" ||
      import.meta.env.VITE_API_BASE_URL ===
        "https://apicarbike.gramcover.com/api";

    if (!custom) {
      if (type === "car") {
        history.push("/car/lead-page");
      }
      if (type === "bike") {
        history.push("/bike/lead-page");
      }
      if (type === "cv") {
        history.push("/cv/lead-page");
      }
    }
    if (custom) {
      //gram-prod
      if (
        import.meta.env.VITE_API_BASE_URL ===
        "https://apicarbike.gramcover.com/api"
      ) {
        if (type === "car") {
          reloadPage("https://car.gramcover.com/car/lead-page");
        }
        if (type === "bike") {
          reloadPage("https://bike.gramcover.com/bike/lead-page");
        }
        if (type === "cv") {
          reloadPage("https://cv.gramcover.com/cv/lead-page");
        }
      }
      //ACE UAT
      if (
        import.meta.env.VITE_API_BASE_URL ===
        "https://uatmotorapis.aceinsurance.com/api"
      ) {
        if (type === "car") {
          reloadPage("https://uatcar.aceinsurance.com/car/lead-page");
        }
        if (type === "bike") {
          reloadPage("https://uatbike.aceinsurance.com/bike/lead-page");
        }
        if (type === "cv") {
          reloadPage("https://uatpcv.aceinsurance.com/cv/lead-page");
        }
      }
      //ACE Prod
      if (
        import.meta.env.VITE_API_BASE_URL ===
        "https://motorapis.aceinsurance.com/api"
      ) {
        if (type === "car") {
          reloadPage("https://car.aceinsurance.com/car/lead-page");
        }
        if (type === "bike") {
          reloadPage("https://bike.aceinsurance.com/bike/lead-page");
        }
        if (type === "cv") {
          reloadPage("https://pcv.aceinsurance.com/cv/lead-page");
        }
      }
    }
  };

  const lessthan768 = useMediaPredicate("(max-width: 768px)");
  const lessthan576 = useMediaPredicate("(max-width: 576px)");

  return (
    <>
      <CircleDiv className="circle"></CircleDiv>
      <div className="blurred-wrapper">
        <MainHeader
          className="main-header containerLp"
          style={{ zIndex: "-1", visibility: "hidden" }}
        >
          {LogoFunction()}
          <a
            role="button"
            tabindex="0"
            onClick={() => reloadPage(UrlFn())}
            className="btnLp sign-up"
          >
            Login
          </a>
        </MainHeader>
        <Section className="hero containerLp">
          <div className="content-wrapper">
            <h5
              className="tagline"
              style={{
                color:
                  lessthan576 &&
                  import.meta.env.VITE_BROKER === "ABIBL" &&
                  "#fff",
              }}
            >
              No speed limits on our service
            </h5>
            <h1
              className="title"
              style={{
                marginRight: "1rem",
                color:
                  lessthan576 &&
                  import.meta.env.VITE_BROKER === "ABIBL" &&
                  "#fff",
              }}
            >
              Compare <ColorText>quotes</ColorText> from every{" "}
              <ColorText>leading insurance company</ColorText> in seconds.
            </h1>
            <LookingText className="messageLp">
              Looking for Vehicle Insurance?
            </LookingText>
            <QuotesButton>
              {AccessControl.includes("car") && (
                <a
                  role="button"
                  tabindex="0"
                  onClick={() => OnTypeSelect("car")}
                  className="btnLp cta car_button text-center"
                  style={{
                    padding: "0px 10px",
                    height: "86px",
                    marginRight: "2rem",
                  }}
                >
                  <StyledDiv className="text-center" title="CAR">
                    {import.meta.env?.VITE_BROKER === "ACE" ? (
                      <DriveEta sx={{ fontSize: 82, color: "#0093c7" }} />
                    ) : (
                      <img
                        src={`${
                          import.meta.env.VITE_BASENAME !== "NA"
                            ? `/${import.meta.env.VITE_BASENAME}`
                            : ""
                        }/assets/images/car.svg`}
                        height="85"
                        width="85"
                        alt=""
                      />
                    )}
                  </StyledDiv>
                  {/* Car */}
                </a>
              )}
              {AccessControl.includes("bike") && (
                <a
                  role="button"
                  tabindex="0"
                  onClick={() => OnTypeSelect("bike")}
                  className="btnLp cta bike_button text-center"
                  style={{
                    padding: "0px 10px",
                    height: "86px",
                    marginRight: "2rem",
                  }}
                >
                  <StyledDiv className="text-center" title="BIKE">
                    {import.meta.env?.VITE_BROKER === "ACE" ? (
                      <TwoWheeler sx={{ fontSize: 85, color: "#0093c7" }} />
                    ) : (
                      <img
                        src={`${
                          import.meta.env.VITE_BASENAME !== "NA"
                            ? `/${import.meta.env.VITE_BASENAME}`
                            : ""
                        }/assets/images/bike.svg`}
                        height="85"
                        width="85"
                      />
                    )}
                  </StyledDiv>
                  {/* Bike */}
                </a>
              )}
              {(AccessControl.includes("pcv") ||
                AccessControl.includes("gcv")) && (
                <a
                  role="button"
                  tabindex="0"
                  onClick={() => OnTypeSelect("cv")}
                  className="btnLp cta cv_button text-center"
                  style={{
                    padding: "0px 10px",
                    height: "86px",
                    marginRight: "2rem",
                  }}
                >
                  <StyledDiv className="text-center" title="COMMERCIAL VEHICLE">
                    {import.meta.env?.VITE_BROKER === "ACE" ? (
                      <LocalShipping sx={{ fontSize: 85, color: "#0093c7" }} />
                    ) : (
                      <img
                        src={`${
                          import.meta.env.VITE_BASENAME !== "NA"
                            ? `/${import.meta.env.VITE_BASENAME}`
                            : ""
                        }/assets/images/cv.svg`}
                        height="85"
                        width="85"
                        alt=""
                      />
                    )}
                  </StyledDiv>
                  {/* Commercial Vehicle */}
                </a>
              )}
            </QuotesButton>
          </div>
          {!lessthan768 && (
            <div>
              <img
                className="car"
                src={`${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/insurance2.png`}
                style={{ width: "700px" }}
                alt=""
              />
            </div>
          )}
        </Section>
      </div>
      <GlobalStyle />
    </>
  );
};

export const GlobalStyle = createGlobalStyle`
body {
  padding: 0;
  margin: 0;
  box-sizing: border-box;
  height: 100vh !important;
  min-height: 100vh !important;
}
`;

const Section = styled.section`
  font-family: ${({ theme }) => theme.LandingPage?.fontFamily || ""};
  font-weight: ${({ theme }) => theme.LandingPage?.fontWeight || ""};
`;

const Logo = styled.img`
  width: ${import.meta.env.VITE_BROKER === "ACE" ||
  import.meta.env.VITE_BROKER === "SRIYAH"
    ? "140px"
    : import.meta.env.VITE_BROKER === "HEROCARE"
    ? "auto"
    : [
        "RB",
        "SPA",
        "BAJAJ",
        "UIB",
        "SRIDHAR",
        "POLICYERA",
        "TATA",
        "KMD",
        "FYNTUNE"
      ].includes(import.meta.env.VITE_BROKER)
    ? "auto"
    : "160px"};
  height: ${import.meta.env.VITE_BROKER !== "FYNTUNE"
    ? import.meta.env.VITE_BROKER === "ACE" ||
      import.meta.env.VITE_BROKER === "SRIYAH"
      ? "65px"
      : import.meta.env.VITE_BROKER === "RB"
      ? "90px"
      : import.meta.env.VITE_BROKER === "SPA"
      ? "62px"
      : import.meta.env.VITE_BROKER === "TATA"
      ? "57px"
      : import.meta.env.VITE_BROKER === "UIB"
      ? "55px"
      : import.meta.env.VITE_BROKER === "SRIDHAR"
      ? "70px"
      : import.meta.env.VITE_BROKER === "POLICYERA"
      ? "45px"
      : import.meta.env.VITE_BROKER === "KMD"
      ? "95px"
      : import.meta.env.VITE_BROKER === "HEROCARE"
      ? "55px"
      : "50px"
    : "38px"};
  @media (max-width: 768px) {
    width: ${[
      "RB",
      "BAJAJ",
      "UIB",
      "SRIDHAR",
      "POLICYERA",
      "TATA",
      "KMD",
    ].includes(import.meta.env.VITE_BROKER)
      ? "auto"
      : "130px"};
    height: ${import.meta.env.VITE_BROKER !== "FYNTUNE"
      ? import.meta.env.VITE_BROKER === "ACE" ||
        import.meta.env.VITE_BROKER === "SRIYAH"
        ? "65px"
        : import.meta.env.VITE_BROKER === "RB" ||
          import.meta.env.VITE_BROKER === "KMD"
        ? "70px"
        : import.meta.env.VITE_BROKER === "BAJAJ"
        ? "40px"
        : import.meta.env.VITE_BROKER === "UIB"
        ? "45px"
        : import.meta.env.VITE_BROKER === "TATA"
        ? "50px"
        : import.meta.env.VITE_BROKER === "SRIDHAR"
        ? "60px"
        : import.meta.env.VITE_BROKER === "POLICYERA"
        ? "40px"
        : "50px"
      : "38px"};
  }

  @media (max-width: 415px) {
    width: ${[
      "RB",
      "BAJAJ",
      "UIB",
      "SRIDHAR",
      "POLICYERA",
      "TATA",
      "KMD",
    ].includes(import.meta.env.VITE_BROKER)
      ? "auto"
      : "130px"};
    height: ${import.meta.env.VITE_BROKER !== "FYNTUNE"
      ? import.meta.env.VITE_BROKER === "ACE" ||
        import.meta.env.VITE_BROKER === "SRIYAH"
        ? "65px"
        : import.meta.env.VITE_BROKER === "RB"
        ? "70px"
        : import.meta.env.VITE_BROKER === "BAJAJ" ||
          import.meta.env.VITE_BROKER === "UIB"
        ? "35px"
        : import.meta.env.VITE_BROKER === "SRIDHAR"
        ? "60px"
        : import.meta.env.VITE_BROKER === "POLICYERA"
        ? "30px"
        : import.meta.env.VITE_BROKER === "TATA"
        ? "45px"
        : "50px"
      : "38px"};
  }
  @media (max-width: 360px) {
    width: ${import.meta.env.VITE_BROKER === "RB"
      ? ""
      : ["BAJAJ", "UIB", "POLICYERA", "TATA", "KMD"].includes(
          import.meta.env.VITE_BROKER
        )
      ? "auto"
      : "100px"};
    height: ${import.meta.env.VITE_BROKER === "BAJAJ"
      ? "30px"
      : import.meta.env.VITE_BROKER === "TATA" && "40px"};
  }
`;

const QuotesButton = styled.div`
  display: flex;
  justify-content: flex-start;
  width: 100%;
  margin-bottom: 10px;

  @media (max-height: 420px) {
    margin-bottom: 80px;
  }

  @media (max-width: 766px) {
    justify-content: center;
    padding-left: 10px;
  }

  @media (max-height: 420px) {
    margin-bottom: 80px;
  }

  .car_button {
    img {
      // filter: brightness(0) invert(1);
      @media (max-width: 768px) {
        width: 50px;
        height: 50px;
      }
    }
    @media (max-width: 768px) {
      padding: 0.5rem 1rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
  }
  .bike_button {
    img {
      // filter: brightness(0) invert(1);
      @media (max-width: 768px) {
        width: 50px;
        height: 50px;
      }
    }
    @media (max-width: 768px) {
      padding: 0.5rem 1rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
  }
  .cv_button {
    img {
      @media (max-width: 768px) {
        width: 50px;
        height: 50px;
      }
    }
    p {
      color: #bdd400;
      // filter: invert(100%);
      font-size: 3rem;
      letter-spacing: 5px;
      @media (max-width: 768px) {
        font-size: 1.5rem;
        margin: 0;
        margin-bottom: 13px;
      }
    }
    padding: 1rem 1.1rem;
    @media (max-width: 768px) {
      padding: 0.5rem 1rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
  }
`;

const CircleDiv = styled.div`
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: ${({ theme }) => theme.LandingPage?.color || "#bdd400"};
  clip-path: circle(810px at right 950px);
  @media (max-width: 1170px) {
    clip-path: circle(860px at right 1110px);
  }
  @media (max-width: 1050px) {
    clip-path: circle(860px at right 1220px);
  }
  @media (max-width: 450px) {
    clip-path: circle(220px at top);
  }
  @media (max-width: 766px) {
    clip-path: circle(383px at top);
    margin-top: 75px;
    height: 85%;
  }
  @media (max-width: 576px) {
    clip-path: circle(290px at top);
  }
  @media (max-width: 450px) {
    clip-path: circle(220px at top);
  }
  @media (max-height: 380px) {
    display: none;
  }
`;
const ColorText = styled.text`
  color: ${({ theme }) => theme.LandingPage?.color || "green"};
  @media (max-width: 1030px) {
    color: ${({ theme }) => theme.LandingPage?.color3 || "green"};
  }
  // @media (max-width: 465px) {
  //   color: ${({ theme }) => theme.LandingPage?.color2 || "green"};
  // }
`;

const LookingText = styled.p`
  color: ${({ theme }) => theme.LandingPage?.color || "rgb(189, 212, 0)"};
  white-space: nowrap;
  @media (max-width: 1170px) {
    color: black;
  }
  @media (max-width: 766px) {
    color: ${({ theme }) =>
      theme.LandingPage?.color || "rgb(189, 212, 0)"} !important;
  }
`;

const StyledDiv = styled.div`
  filter: ${({ theme }) =>
    import.meta.env.VITE_BROKER === "ACE"
      ? ""
      : theme.LandingPage?.filter || "none"};
`;

const MainHeader = styled.header`
  @media (max-width: 766px) {
    height: 5rem;
    .btnLp {
      padding: 0.6rem 0.8rem;
    }
  }
  @media (max-width: 450px) {
    padding: 0px;
    img {
      margin-left: 20px;
    }
    a {
      margin-right: 20px;
    }
    .loginDrop {
      margin-right: 10px;
    }
  }

  @media (max-width: 320px) {
    img {
      margin: 0px !important;
    }
    a {
      margin-right: 0px !important;
    }
  }

  .loginBtn {
    background: ${({ theme }) =>
      theme.LandingPage?.loginBtnColor || "#bdd400 !important"};
  }
  .dropItem.active,
  .dropItem:active {
    background: ${({ theme }) =>
      theme.LandingPage?.loginBtnColor || "#bdd400 !important"};
  }
`;
