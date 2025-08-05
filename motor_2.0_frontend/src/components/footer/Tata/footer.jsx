import React, { useState } from "react";
import { useMediaPredicate } from "react-media-hook";
import { Row, Col } from "react-bootstrap";
import Linkedin from "assets/img/linkedin.png";
import { TypeReturn } from "modules/type";
import { useSelector } from "react-redux";
import { useLocation } from "react-router";
import _ from "lodash";
import RemoveIcon from "@mui/icons-material/Remove";
import Tata from "../../../utils/img/tata.png";
import Copyright from "../../../utils/img/tata-title.png";
import LocalHospitalIcon from "@mui/icons-material/LocalHospital";
import CallIcon from "@mui/icons-material/Call";
import MailOutlineIcon from "@mui/icons-material/MailOutline";
import DirectionsCarIcon from "@mui/icons-material/DirectionsCar";
import TwoWheelerIcon from "@mui/icons-material/TwoWheeler";
import {
  GlobalStyle,
  Logo,
  Content,
  MediaContainer,
  FooterTag,
  FooterContainer,
  ContactText,
  FooterTitle,
  Line,
  MiddleFooter,
  TollText,
  InsideText,
  Main,
  CopyrightText,
} from "./tataFooterStyle";

const Footer = () => {
  const { theme_conf } = useSelector((state) => state.home);
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan576 = useMediaPredicate("(max-width: 576px)");
  const location = useLocation();
  const loc = location.pathname ? location.pathname.split("/") : "";
  const type = !_.isEmpty(loc) ? (loc?.length >= 2 ? loc[1] : "") : "";
  const { temp_data: temp } = useSelector((state) => state.proposal);
  const [listdisplay, setListDisplay] = useState(false);
  return (
    <>
      <FooterTag
        style={{
          backgroundColor: "#374ddb",
          textAlign: lessthan767 ? "left" : "center",
          padding: "60px 0 0px 0",
          display: "flex",
          flexDirection: "column",
        }}
      >
        <Main>
          <a
            href={`${window.location.origin}${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ``
            }/${
              Number(temp?.productSubTypeId) === 1
                ? "car"
                : Number(temp?.productSubTypeId) === 2
                ? "bike"
                : type && ["car", "bike", "cv"].includes(TypeReturn(type))
                ? type
                : temp?.journeyCategory
                ? temp?.journeyCategory.toLowerCase() === "pcv" ||
                  temp?.journeyCategory.toLowerCase() === "gcv"
                  ? "cv"
                  : temp?.journeyCategory.toLowerCase()
                : "cv"
            }/lead-page`}
          >
            <Logo
              // style={{}}
              src={Tata}
              alt="logo"
            />
          </a>
          <Line
            style={{
              marginLeft: lessthan767 ? "0" : "13.1%",
              marginTop: "-30px",
            }}
          />
          <Row
            style={{ paddingTop: "30px" }}
            onMouseLeave={() => setListDisplay(false)}
          >
            <Col
              xl={3}
              lg={3}
              md={3}
              sm={6}
              sx={6}
              style={{ marginTop: lessthan767 ? "" : "" }}
            >
              <FooterContainer onMouseEnter={() => setListDisplay(true)}>
                {/* <img
                  className="tickLogo"
                  src={tick}
                  style={{ height: "37px", width: "15px" }}
                  alt=""
                /> */}
                {/* <CheckIcon
                  sx={{ fontSize: "24px", color: "#ffffff", fontWeight: 700 }}
                /> */}
                {/* <a href="abc"> */}
                <FooterTitle style={{ cursor: "pointer" }}>
                  Products
                </FooterTitle>
                {/* </a> */}
              </FooterContainer>
              <InsideText
                style={
                  listdisplay
                    ? {}
                    : lessthan767
                    ? { display: "none" }
                    : { display: "none" }
                }
              >
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/health-insurance`}
                >
                  <LocalHospitalIcon
                    sx={{
                      fontSize: "20px",
                      color: "#ffffff",
                      fontWeight: 700,
                      marginRight: "4px",
                    }}
                  />{" "}
                  Health Insurance
                </a>
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/car-insurance`}
                >
                  <DirectionsCarIcon
                    sx={{
                      fontSize: "20px",
                      color: "#ffffff",
                      fontWeight: 700,
                      marginRight: "4px",
                    }}
                  />{" "}
                  Car Insurance
                </a>
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/bike-insurance`}
                >
                  <TwoWheelerIcon
                    sx={{
                      fontSize: "20px",
                      color: "#ffffff",
                      fontWeight: 700,
                      marginRight: "4px",
                    }}
                  />{" "}
                  Bike Insurance
                </a>
              </InsideText>
              <FooterContainer>
                {/* <img
                  src={tick}
                  className="tickLogo"
                  style={{ height: "37px", width: "15px" }}
                  alt=""
                /> */}
                {/* <CheckIcon sx={{ fontSize: "24px", color: "#ffffff" }} /> */}
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat`
                  }dashboard.lifekaplan.com/customer/login`}
                >
                  <FooterTitle>Claims</FooterTitle>
                </a>
              </FooterContainer>
              <InsideText
                style={
                  listdisplay
                    ? { display: "none" }
                    : lessthan767
                    ? { display: "none" }
                    : { visibility: "hidden" }
                }
              >
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/health-insurance`}
                >
                  <RemoveIcon
                    sx={{ fontSize: "24px", color: "#ffffff", fontWeight: 700 }}
                  />{" "}
                  Health Insurance
                </a>
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/car-insurance`}
                >
                  <RemoveIcon
                    sx={{ fontSize: "24px", color: "#ffffff", fontWeight: 700 }}
                  />{" "}
                  Car Insurance
                </a>
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/bike-insurance`}
                >
                  <RemoveIcon
                    sx={{ fontSize: "24px", color: "#ffffff", fontWeight: 700 }}
                  />{" "}
                  Bike Insurance
                </a>
              </InsideText>
            </Col>
            <Col xl={3} lg={3} md={3} sm={6} sx={6}>
              <FooterContainer>
                {/* <img
                  src={tick}
                  className="tickLogo"
                  style={{ height: "37px", width: "15px" }}
                  alt=""
                /> */}
                {/* <CheckIcon sx={{ fontSize: "24px", color: "#ffffff" }} /> */}
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/need-help`}
                >
                  <FooterTitle>Need Help?</FooterTitle>
                </a>
              </FooterContainer>
              {/* <FooterContainer>
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/health-insurance#`}
                >
                  <FooterTitle>Insurance made simple</FooterTitle>
                </a>
              </FooterContainer> */}
              <FooterContainer>
                {/* <img
                  src={tick}
                  className="tickLogo"
                  style={{ height: "37px", width: "15px" }}
                  alt=""
                /> */}
                {/* <CheckIcon sx={{ fontSize: "24px", color: "#ffffff" }} /> */}
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/#testi`}
                >
                  <FooterTitle>Our Happy Customers</FooterTitle>
                </a>
              </FooterContainer>
              <FooterContainer>
                {/* <img
                  src={tick}
                  className="tickLogo"
                  style={{ height: "37px", width: "15px" }}
                  alt=""
                /> */}
                {/* <CheckIcon sx={{ fontSize: "24px", color: "#ffffff" }} /> */}
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/#partners`}
                >
                  <FooterTitle>Insurance Partners</FooterTitle>
                </a>
              </FooterContainer>
            </Col>
            <Col
              xl={3}
              lg={3}
              md={3}
              sm={6}
              sx={6}
              style={{
                borderRight: !lessthan767 ? "1px  solid white" : "",
                borderBottom: lessthan576 ? "1px solid white" : "",
              }}
            >
              <FooterContainer>
                {/* <img
                  src={tick}
                  className="tickLogo"
                  style={{ height: "37px", width: "15px" }}
                  alt=""
                /> */}
                {/* <CheckIcon sx={{ fontSize: "24px", color: "#ffffff" }} /> */}
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat`
                  }dashboard.lifekaplan.com/customer/login`}
                >
                  <FooterTitle>Log in</FooterTitle>
                </a>
              </FooterContainer>
              <FooterContainer>
                {/* <img
                  src={tick}
                  className="tickLogo"
                  style={{ height: "37px", width: "15px" }}
                  alt=""
                /> */}
                {/* <CheckIcon sx={{ fontSize: "24px", color: "#ffffff" }} /> */}
                <a href="https://www.tatamotorsinsurancebrokers.com/our-story">
                  <FooterTitle>About Us</FooterTitle>
                </a>
              </FooterContainer>
              <FooterContainer>
                {/* <img
                  src={tick}
                  className="tickLogo"
                  style={{ height: "37px", width: "15px" }}
                  alt=""
                /> */}
                {/* <CheckIcon sx={{ fontSize: "24px", color: "#ffffff" }} /> */}
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/contact-us`}
                >
                  <FooterTitle>Contact Us</FooterTitle>
                </a>
              </FooterContainer>
              <FooterContainer>
                {/* <img
                  src={tick}
                  className="tickLogo"
                  style={{ height: "37px", width: "15px" }}
                  alt=""
                /> */}
                {/* <CheckIcon sx={{ fontSize: "24px", color: "#ffffff" }} /> */}
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/grievance-redressal`}
                >
                  <FooterTitle>Grievance Redressal</FooterTitle>
                </a>
              </FooterContainer>
            </Col>
            <Col xl={3} lg={3} md={3} sm={6} sx={6}>
              <MediaContainer>
                <div
                  style={{
                    color: "#fff",
                    fontSize: "16px",
                    fontWeight: "bold",
                    position: "relative",
                    marginTop: lessthan767 ? "30px" : "",
                  }}
                >
                  {/* <a href="https://twitter.com/Bajaj_insurance">
                    <img className="socialIcon" src={twitter} alt="" />
                  </a>
                  <a href="https://www.instagram.com/bajaj_capital_insurance/">
                    <img className="socialIcon" src={instagram} alt="" />
                  </a> */}
                  Follow us on:{" "}
                  <a
                    href={`https://${
                      import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                    }lifekaplan.com/health-insurance#`}
                  >
                    <img
                      className="socialIcon"
                      src={Linkedin}
                      style={{ padding: "8px", marginLeft: "6px" }}
                      alt=""
                    />
                  </a>
                </div>
                <div style={{ marginTop: "15px", color: "#fff" }}>
                  <ContactText style={{ marginBottom: "1rem" }}>
                    Contact us on
                  </ContactText>
                  <ContactText>
                    <CallIcon sx={{ fontSize: "18px" }} />
                    {theme_conf?.broker_config?.phone
                      ? theme_conf?.broker_config?.phone
                      : "18002090060"}
                  </ContactText>
                  <ContactText>
                    <MailOutlineIcon sx={{ fontSize: "18px" }} />
                    {theme_conf?.broker_config?.email
                      ? theme_conf?.broker_config?.email
                      : "support@tmibasl.com"}
                  </ContactText>
                </div>
              </MediaContainer>
            </Col>
          </Row>
        </Main>
        <MiddleFooter>
          <Content>
            <CopyrightText src={Copyright} alt="" />
            <TollText>
              Composite Broker License No.{" "}
              {theme_conf?.broker_config?.irdanumber
                ? theme_conf?.broker_config?.irdanumber
                : "375"}{" "}
              I Validity 13/05/2023 to 12/05/2026 I CIN:{" "}
              {theme_conf?.broker_config?.cinnumber
                ? theme_conf?.broker_config?.cinnumber
                : "U50300MH1997PLC149349"}{" "}
              | IBAI Membership No. 35375 <br />
              ISO 9001:2015 certified for Quality Management System (QMS) <br />
              Corp Office: 1st Floor AFL House, Lok Bharti complex, Marol
              Maroshi Road, Andheri (East), Mumbai - 400 059. Maharashtra.
              India. <br />
              Registered Office: Nanavati Mahalaya, 3rd floor, Tamarind Lane,
              Homi Mody Street, Fort, Mumbai - 400 001. Maharashtra. India.{" "}
              <br />A sister Company of TATA AIA Life Insurance Company Limited
              and TATA AIG General Insurance Company Limited |{" "}
              <span>
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/terms-conditions`}
                >
                  Terms & Conditions |
                </a>
              </span>{" "}
              <span>
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/code-of-conduct`}
                >
                  Broker Code of Conduct |
                </a>
              </span>{" "}
              <span>
                <a
                  href={`https://${
                    import.meta.env.VITE_PROD === "YES" ? `` : `uat.`
                  }lifekaplan.com/privacy-policy`}
                >
                  Privacy Policy |
                </a>
              </span>{" "}
              <span>
                <a href="https://www.irdai.gov.in/">IRDAI |</a>
              </span>{" "}
              <span>
                <a href="http://ibai.org/">IBAI</a>
              </span>{" "}
            </TollText>
            {/* <TollText>
              Corp Office: 1st Floor AFL House, Lok Bharti complex, Marol
              Maroshi Road, Andheri (East), Mumbai - 400 059. Maharashtra.
              India.
            </TollText> */}
            {/* <TollText>
              Registered Office: Nanavati Mahalaya, 3rd floor, Tamarind Lane,
              Homi Mody Street, Fort, Mumbai - 400 001. Maharashtra. India.
            </TollText> */}
            {/* <TollText>
              A sister Company of TATA AIA Life Insurance Company Limited and
              TATA AIG General Insurance Company Limited |{" "}
              <span>
                <a href="https://uat.lifekaplan.com/terms-conditions">
                  Terms & Conditions |
                </a>
              </span>{" "}
              <span>
                <a href="https://uat.lifekaplan.com/code-of-conduct">
                  Broker Code of Conduct |
                </a>
              </span>{" "}
              <span>
                <a href="https://uat.lifekaplan.com/privacy-policy">
                  Privacy Policy |
                </a>
              </span>{" "}
              <span>
                <a href="https://www.irdai.gov.in/">IRDAI |</a>
              </span>{" "}
              <span>
                <a href="http://ibai.org/">IBAI</a>
              </span>{" "}
            </TollText> */}
          </Content>
        </MiddleFooter>
        <GlobalStyle lessthan767={lessthan767} />
      </FooterTag>
    </>
  );
};

export default Footer;
