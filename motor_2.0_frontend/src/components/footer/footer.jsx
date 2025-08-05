import React from "react";
import styled, { createGlobalStyle } from "styled-components";
import { useMediaPredicate } from "react-media-hook";
import { Row, Col, Button } from "react-bootstrap";
import { useSelector } from "react-redux";
import { getIRDAI, cinNO, BrokerCategory, BrokerName } from "components";
import { useHistory } from "react-router-dom";
export const Footer = () => {
  const history = useHistory();
  const todayDate = new Date();
  const presentYear = todayDate.getFullYear();
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const { theme_conf } = useSelector((state) => state.home);

  const FooterFn = () => {
    return (
      <Row>
        {import.meta.env.VITE_BROKER !== "ACE" ? (
          <Col xl={8} lg={8} md={12} sm={12} className="footerBold">
            <div className="footerInline">
              <span>Category: </span>
              {theme_conf?.broker_config?.BrokerCategory || BrokerCategory()}
            </div>
            <div className="footerInline">
              <span>CIN No. </span>
              {theme_conf?.broker_config?.cinnumber || cinNO()}
            </div>
            <div className="footerInline">
              <span>IRDAI Registration No. </span>
              {theme_conf?.broker_config?.irdanumber || getIRDAI()}
            </div>
          </Col>
        ) : (
          <Col xl={6} lg={6} md={12} sm={12} className="footerBold">
            <div className="footerInline">
              <span>Category: </span>
              {theme_conf?.broker_config?.BrokerCategory || BrokerCategory()}
            </div>
            <div className="footerInline">
              <span>CIN No. </span>
              {theme_conf?.broker_config?.cinnumber || cinNO()}
            </div>
            <div className="footerInline">
              <span>IRDAI Registration No. </span>
              {theme_conf?.broker_config?.irdanumber || getIRDAI()}
            </div>
          </Col>
        )}
        <Col
          xl={3}
          lg={4}
          md={12}
          sm={12}
          className="footerCopy"
          style={{
            ...(lessthan767 &&
              window.location.href.includes("quotes") && {
                marginBottom: "40px",
              }),
          }}
        >
          <text style={{ fontWeight: "700" }}>
            {" "}
            Copyright &copy;{" "}
            {import.meta.env.VITE_BROKER === "UIB" ||
            import.meta.env.VITE_BROKER === "RB"
              ? "2026"
              : presentYear}{" "}
          </text>{" "}
          <span></span>
          {theme_conf?.broker_config?.BrokerName || BrokerName()}
        </Col>
        {import.meta.env.VITE_BROKER === "ACE" && (
          <Col
            xl={3}
            lg={2}
            md={12}
            sm={12}
            className="footerCopy"
            style={{ marginTop: "-9px" }}
          >
            <Button
              style={{
                fontWeight: "700",
                color: "lightgreen",
              }}
              variant="link"
              onClick={() => history.push("/privacy")}
            >
              Privacy Policy
            </Button>
          </Col>
        )}
      </Row>
    );
  };

  return (
    <>
      <footer className="footer_style">{FooterFn()}</footer>
      <GlobalStyle />
    </>
  );
};

export default Footer;

const GlobalStyle = createGlobalStyle`
  .footer_style{
    background: ${({ theme }) => theme.footer?.background}!important;
  }

`;

export const Layout = styled.div`
  margin-bottom: ${(props) =>
    import.meta.env.VITE_BROKER === "BAJAJ" &&
    import.meta.env.VITE_BASENAME === "general-insurance"
      ? "329px"
      : import.meta.env.VITE_BROKER === "RB"
      ? "400px"
      : props.marginBottom
      ? "0px !important"
      : "100px"};
  @media (max-width: 767px) {
    margin-bottom: ${(props) =>
      import.meta.env.VITE_BROKER === "RB"
        ? "400px"
        : props.marginBottom
        ? "0px !important"
        : "100px"};
  }
`;
