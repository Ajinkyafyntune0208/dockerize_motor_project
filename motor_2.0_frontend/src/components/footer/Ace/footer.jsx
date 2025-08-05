import React from "react";
import { Row, Col } from "react-bootstrap";
import {
  Logo,
  Content,
  MediaContainer,
  FooterTag,
  Line,
  BottomFooter,
  MiddleFooter,
  GlobalStyle,
} from "./aceFooterStyle";
import facebook from "assets/img/facebook.png";
import twitter from "assets/img/twittor.png";
import instagram from "assets/img/instra.png";
import linkedIn from "assets/img/in.png";
import { useMediaPredicate } from "react-media-hook";
const Footer = () => {
  const lessthan767 = useMediaPredicate("(max-width: 767px)");

  const url = window.location.origin;
  const quickLinks = [
    {
      text: `Services`,
      link: ``,
    },
    {
      text: `Insights`,
      link: ``,
    },
    {
      text: `Contact`,
      link: ``,
    },
  ];

  const legalPolicy = [
    {
      text: `Tel: 011-23352628/23357039/23713098`,
      link: ``,
    },
    {
      text: `mail@aceinsurance.com`,
      link: ``,
    },
    {
      text: `Regd. Office: \nB-17 Ashadeep Building 9 Hailey Road, New Delhi-110001`,
      link: ``,
    },
  ];

  const followSection = [
    { icon: facebook, link: `` },
    {
      icon: twitter,
      link: ``,
    },
    {
      icon: instagram,
      link: ``,
    },
    {
      icon: linkedIn,
      link: ``,
    },
  ];

  return (
    <>
      <FooterTag
        style={{
          backgroundColor: "#fff",
          textAlign: lessthan767 ? "left" : "center",
          display: "flex",
          paddingTop: "1rem",
        }}
      >
        <div style={{ width: "100%" }}>
          <Row
            style={{
              padding: lessthan767 ? "30px 15px" : "38px 13.4% 0 13.4%",
            }}
          >
            <Col xl={4} lg={4} md={4} sm={6} xs={6}>
              <div
                style={{
                  display: "flex",
                  flexDirection: "column",
                  textAlign: "left",
                }}
              >
                <a>
                  <Logo
                    style={{
                      objectFit: "contain",
                      width: "150px",
                      height: "auto",
                    }}
                    src={`${
                      import.meta.env.VITE_BASENAME !== "NA"
                        ? `/${import.meta.env.VITE_BASENAME}`
                        : ""
                    }/assets/images/vehicle/ace.png`}
                    alt="logo"
                  />
                </a>
                <MediaContainer
                  style={{
                    alignItems: "left",
                    marginTop: lessthan767 ? "30px" : "",
                  }}
                >
                  <div
                    style={{
                      paddingTop: "10px",
                      display: "flex",
                    }}
                  >
                    {followSection?.map(({ icon, link }) => (
                      <a href={link} target="_blank">
                        <img
                          src={icon}
                          alt="social icon"
                          style={{ paddingRight: "10px" }}
                        />
                      </a>
                    ))}
                  </div>
                </MediaContainer>
              </div>
            </Col>
            <Col
              xl={2}
              lg={2}
              md={2}
              sm={6}
              sx={6}
              style={{ marginTop: lessthan767 ? "30px" : "" }}
            >
              {/* <FooterTitle>Our Services</FooterTitle> */}
              <div>
                <p className="underline-on-hover">Company</p>
                <p className="underline-on-hover">Carrier</p>
                <p className="underline-on-hover">
                  {" "}
                  <a target="_blank" href={`${url}/privacy`}>
                    Privacy Policy
                  </a>
                </p>
              </div>
            </Col>
            <Col xl={2} lg={2} md={2} sm={6} sx={6}>
              {/* <FooterTitle>Quick Links</FooterTitle> */}
              <div style={{ marginTop: lessthan767 ? "30px" : "" }}>
                {quickLinks?.map(({ text, link }) => (
                  <p className="underline-on-hover">{text}</p>
                ))}
              </div>
            </Col>
            <Col xl={4} lg={4} md={4} sm={6} sx={6}>
              {/* <FooterTitle>Legal Policy</FooterTitle> */}
              <div style={{ marginTop: lessthan767 ? "30px" : "" }}>
                {legalPolicy?.map(({ text, link }) => (
                  <p className="underline">{text}</p>
                ))}
              </div>
            </Col>
          </Row>
          <Line
            style={{
              marginLeft: lessthan767 ? "0" : "13.4%",
              marginRight: "13.4%",
            }}
          />
          <MiddleFooter>
            <Content
              style={{
                padding: lessthan767 ? "30px 15px" : "10px 13.4% 0",
              }}
            >
              IRDAI License No: 246. Period -19.02.22 to 18.02.25 Category
              Composite (CIN NO:U74999DL2008PTC0729)
            </Content>
          </MiddleFooter>
          <BottomFooter>
            <p style={{ textAlign: "center", padding: "0 15px" }}>
              © 2022 Ace Insurance Brokers (P) Limited. All Rights are reserved
            </p>
          </BottomFooter>
          <GlobalStyle lessthan767={lessthan767} />
        </div>
      </FooterTag>
    </>
  );
};

export default Footer;
