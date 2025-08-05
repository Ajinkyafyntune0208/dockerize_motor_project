import React from "react";
import { useSelector } from "react-redux";
import { useMediaPredicate } from "react-media-hook";
import { Row, Col } from "react-bootstrap";
import facebook from "assets/img/facebook.svg";
import xtwitter from "assets/img/twitterxlogo.png";
import instagram from "assets/img/instagram.svg";
import { reloadPage } from "utils";
// prettier-ignore
import {GlobalStyle,Logo,Content,MediaContainer,FooterTag,FooterTitle,Line,
  BottomFooter,Address,Link,MiddleFooter,} from "./BajajFooteStyle";
import { ourBCLServices, quickBCLLinks, legalBCLPolicy, followSection } from "./BajajHelper";

const Footer = () => {
  const { theme_conf } = useSelector((state) => state.home);
  const lessthan767 = useMediaPredicate("(max-width: 767px)");

  return (
    <>
      <FooterTag
        style={{
          backgroundColor: "#fff",
          textAlign: lessthan767 ? "left" : "center",
          display: "flex",
        }}
      >
        <div>
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
                      width: "auto",
                      height: "30px",
                    }}
                    src={`${
                      import.meta.env.VITE_BASENAME !== "NA"
                        ? `/${import.meta.env.VITE_BASENAME}`
                        : ""
                    }/assets/images/vehicle/bajajNew.png`}
                    alt="logo"
                  />
                </a>
                <Address>
                  Bajaj Capital Insurance Broking Limited Bajaj House, 97, Nehru
                  Place, New Delhi -110019.
                </Address>
                <Link href="mailto:care@bajajacapital.com">
                  {theme_conf?.broker_config?.email
                    ? theme_conf?.broker_config?.email
                    : "care@bajajacapital.com"}
                </Link>
                <Link href="tel:1800-212-123123">
                  {theme_conf?.broker_config?.phone
                    ? theme_conf?.broker_config?.phone
                    : "1800 - 212 - 123123"}
                </Link>
              </div>
            </Col>
            <Col
              xl={2}
              lg={2}
              md={2}
              sm={6}
              sx={6}
              style={{ marginTop: lessthan767 ? "" : "" }}
            >
              <FooterTitle>Our Services</FooterTitle>
              <div style={{}}>
                {ourBCLServices?.map(({ text, link }) => (
                  <p
                    className="underline-on-hover"
                    onClick={() => reloadPage(link)}
                  >
                    {text}
                  </p>
                ))}
              </div>
            </Col>
            <Col xl={2} lg={2} md={2} sm={6} sx={6}>
              <FooterTitle>Quick Links</FooterTitle>
              <div style={{}}>
                {quickBCLLinks?.map(({ text, link }) => (
                  <p
                    className="underline-on-hover"
                    onClick={() => reloadPage(link)}
                  >
                    {text}
                  </p>
                ))}
              </div>
            </Col>
            <Col xl={2} lg={2} md={2} sm={6} sx={6}>
              <FooterTitle>Legal Policy</FooterTitle>
              <div style={{}}>
                {legalBCLPolicy?.map(({ text, link }) => (
                  <p
                    className="underline-on-hover"
                    onClick={() => reloadPage(link)}
                  >
                    {text}
                  </p>
                ))}
              </div>
            </Col>
            <Col xl={2} lg={2} md={2} sm={6} sx={6}>
              <FooterTitle
                style={{ textAlign: lessthan767 ? "left" : "center" }}
              >
                Follow Us
              </FooterTitle>
              <MediaContainer
                style={{ alignItems: lessthan767 ? "left" : "center" }}
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
              {` This site is owned and operated by Bajaj Capital Insurance Broking
              Limited [“BCIBL” (CIN:${
                theme_conf?.broker_config?.cinnumber || `U67200DL2002PLC117625`
              })], an IRDA licensed
              Composite Broker bearing`}{" "}
              <span>
                <a
                  target="_blank"
                  href={`https://partner${
                    import.meta.env.VITE_PROD === "YES" ? "" : "uat"
                  }.bajajcapitalinsurance.com/externalpdf/BrokingCertificate.pdf`}
                >
                  License No. 241, License Code CB 042/02,
                </a>
              </span>{" "}
              license dated 09-01-2022 valid till 08-01-2025 (originally
              licensed by IRDA on 09/01/2004 and renewed thereafter).BCIBL is a
              member of Insurance Brokers Association of India (Membership
              No.119). The Prospect’s/visitor’s particulars could be shared with
              users. The information displayed on this website is of the
              insurers with whom BCIBL has an agreement. Insurance is the
              subject matter of solicitation.
            </Content>
          </MiddleFooter>
          <BottomFooter>
            <p style={{ textAlign: "center" }}>
              Copyright © 2020 Bajaj Capital Insurance Broking Limited
            </p>
          </BottomFooter>
          <GlobalStyle lessthan767={lessthan767} />
        </div>
      </FooterTag>
    </>
  );
};

export default Footer;
