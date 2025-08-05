import React from "react";
import styled from "styled-components";
import { Row, Col } from "react-bootstrap";

const HealthList = {
  [`Family Health Insurance`]:
    "https://www.renewbuy.com/health-insurance/family-health-insurance-plan",
  [`Senior Citizen Health Insurance`]:
    "https://www.renewbuy.com/health-insurance/senior-citizen-health-insurance",
  [`Mediclaim Policy`]:
    "https://www.renewbuy.com/health-insurance/mediclaim-policy",
  [`Individual Health Insurance`]:
    "https://www.renewbuy.com/health-insurance/individual-health-insurance-plan",
  [`Compare Health Insurance`]:
    "https://www.renewbuy.com/health-insurance/compare-health-insurance",
  [`Health Insurance Calculator`]:
    "https://www.renewbuy.com/health-insurance/health-insurance-calculator",
  [`Health Insurance Companies In India`]:
    "https://www.renewbuy.com/health-insurance/companies",
};

const LifeList = {
  [`Term Insurance Claim`]:
    "https://www.renewbuy.com/life-insurance/term-insurance/claim-settlement",
  [`Term Life Insurance with Spouse`]:
    "https://www.renewbuy.com/life-insurance/term-insurance/spouse",
  [`Life Insurance Claim Settlement Ratio`]:
    "https://www.renewbuy.com/articles/life-insurance/claim-settlement-ratio",
  [`Life Insurance Companies in India`]:
    "https://www.renewbuy.com/life-insurance/companies",
  [`Term Insurance`]: "https://www.renewbuy.com/life-insurance/term-insurance",
  [`ULIP - Unit Linked Insurance Plan`]:
    "https://www.renewbuy.com/life-insurance/ulip-plans",
};

const MotorList = {
  [`Car Insurance`]: "https://www.renewbuy.com/motor-insurance/car-insurance",
  [`Car Insurance Claim`]:
    "https://www.renewbuy.com/motor-insurance/car-insurance-claim",
  [`Two-Wheeler Insurance`]:
    "https://www.renewbuy.com/motor-insurance/two-wheeler-insurance",
  [`Third-Party Bike Insurance`]:
    "https://www.renewbuy.com/motor-insurance/third-party-two-wheeler-insurance",
  [`Third-Party Car Insurance`]:
    "https://www.renewbuy.com/motor-insurance/car-insurance/third-party-insurance",
  [`Third-Party Insurance`]:
    "https://www.renewbuy.com/motor-insurance/third-party-insurance",
  [`Commercial Vehicle Insurance`]:
    "https://www.renewbuy.com/motor-insurance/commercial-vehicle-insurance",
};

const OtherList = {
  [`Become An Advisor`]: "https://www.renewbuy.com/become-an-advisor",
  [`Partner Login`]: "https://partners.renewbuy.com/#/login",
  [`Claim Support Numbers`]:
    "https://www.renewbuy.com/renewbuy-partners/insurer-contacts/",
  [`One Click Renewal`]: "https://www.renewbuy.com/glovebox/",
};

const Footer = () => {
  return (
    <StyledDiv>
      <footer>
        {/* <Row className="px-1 mx-2">
          <Col lg="3">
            <div className="logo-container">
              <img className="logo" src={rb} alt="Renewbuy Logo" />
            </div>
            <div>
              <p className="description">
                RenewBuyInsurance.com is one of the fastest-growing online
                insurance distribution platforms, which simplifies insurance and
                makes it accessible for its customers through digitally enabled
                advisors.
              </p>
            </div>
            <div>
              <p className="follow">Follow us on</p>
            </div>
          </Col>
          <Col lg="2" style={{ marginTop: "18px" }} className="px-0 mx-0">
            <div className="list-container">
              <a
                href="https://www.renewbuy.com/health-insurance/"
                className="heading-a-tag"
              >
                Health Insurance
              </a>
              <div>
                {Object.keys(HealthList).map((i) => (
                  <ul className="ul-style">
                    <a className="a-tag" href={HealthList?.i || ""}>
                      {i}
                    </a>
                  </ul>
                ))}
              </div>
            </div>
          </Col>
          <Col lg="2" style={{ marginTop: "18px" }} className="px-0 mx-0">
            <div className="list-container">
              <a
                href="https://www.renewbuy.com/life-insurance"
                className="heading-a-tag"
              >
                Life Insurance
              </a>
              <div>
                {Object.keys(LifeList).map((i) => (
                  <ul className="ul-style">
                    <a className="a-tag" href={LifeList?.i || ""}>
                      {i}
                    </a>
                  </ul>
                ))}
              </div>
            </div>
          </Col>
          <Col lg="2" style={{ marginTop: "18px" }} className="px-0 mx-0">
            <div className="list-container">
              <a
                href="https://www.renewbuy.com/motor-insurance/"
                className="heading-a-tag"
              >
                Motor Insurance
              </a>
              <div>
                {Object.keys(MotorList).map((i) => (
                  <ul className="ul-style">
                    <a className="a-tag" href={MotorList?.i || ""}>
                      {i}
                    </a>
                  </ul>
                ))}
              </div>
            </div>
          </Col>
          <Col lg="2" style={{ marginTop: "18px" }} className="px-0 mx-0">
            <div className="list-container">
              <a className="heading-a-tag">Other</a>
              <div>
                {Object.keys(OtherList).map((i) => (
                  <ul className="ul-style">
                    <a className="a-tag" href={OtherList?.i || ""}>
                      {i}
                    </a>
                  </ul>
                ))}
              </div>
            </div>
          </Col>
        </Row>
        <Row className="px-1 mx-2 mt-3">
          <div className="discover w-100 d-flex align-content-center">
            <Col lg="9" className="w-100">
              <div className="w-100 d-flex">
                <a className="list-a">Articles</a>
                <a className="list-a">Press</a>
                <a className="list-a">Contact Us</a>
                <a className="list-a">Grievance Redressal</a>
                <a className="list-a">Privacy Policy</a>
                <a className="list-a">Terms & Conditions</a>
                <a className="list-a">Company Disclosure</a>
                <a className="list-a">Disclaimer</a>
                <a className="list-a">Principal Officer</a>
              </div>
            </Col>
            <Col lg="3" className="w-100">
              <div className="d-flex">
                <p className="discover-text mb-0 p-0">Discover Our Apps</p>
                <a
                  className="mx-1"
                  href="https://play.google.com/store/apps/details?id=com.renewbuy.partners"
                >
                  <svg
                    className="apps-footer"
                    height="54"
                    viewBox="0 0 54 54"
                    width="54"
                    xmlns="http://www.w3.org/2000/svg"
                  >
                    {" "}
                    <g
                      data-name="353462_android store_app store_google_google play logo_play_icon"
                      id="_353462_android_store_app_store_google_google_play_logo_play_icon"
                      transform="translate(-0.005 0.207)"
                    >
                      {" "}
                      <g
                        data-name="Group 85384"
                        id="Group_85384"
                        transform="translate(0.005 0.013)"
                      >
                        {" "}
                        <circle
                          cx="27"
                          cy="27"
                          data-name="Ellipse 208"
                          fill="#52c6d8"
                          id="Ellipse_208"
                          opacity="0.2"
                          r="27"
                          transform="translate(0 -0.22)"
                        ></circle>{" "}
                        <path
                          d="M75,.013v53.78A26.89,26.89,0,0,0,75,.013Z"
                          data-name="Path 98262"
                          fill="#52c6d8"
                          id="Path_98262"
                          opacity="0.2"
                          transform="translate(-48.115 -0.013)"
                        ></path>{" "}
                        <circle
                          cx="23.479"
                          cy="23.479"
                          data-name="Ellipse 209"
                          fill="#52c6d8"
                          id="Ellipse_209"
                          r="23.479"
                          transform="translate(3.411 3.411)"
                        ></circle>{" "}
                        <path
                          d="M75,9.525V56.484A23.479,23.479,0,1,0,75,9.525Z"
                          data-name="Path 98263"
                          fill="#4db8c9"
                          id="Path_98263"
                          transform="translate(-48.115 -6.115)"
                        ></path>{" "}
                        <g
                          data-name="Group 85383"
                          id="Group_85383"
                          transform="translate(3.41 25.84)"
                        >
                          {" "}
                          <path
                            d="M9.544,72.086c-.015.348-.027.7-.027,1.049A23.479,23.479,0,0,0,33,96.615v-2.1A23.478,23.478,0,0,1,9.544,72.086Z"
                            data-name="Path 98264"
                            fill="#4db8c9"
                            id="Path_98264"
                            transform="translate(-9.517 -72.086)"
                          ></path>{" "}
                          <path
                            d="M98.458,72.086a23.478,23.478,0,0,1-23.453,22.43v2.1a23.479,23.479,0,0,0,23.48-23.48C98.485,72.783,98.473,72.434,98.458,72.086Z"
                            data-name="Path 98265"
                            fill="#47acbc"
                            id="Path_98265"
                            transform="translate(-51.525 -72.086)"
                          ></path>{" "}
                        </g>{" "}
                      </g>{" "}
                      <g
                        data-name="Group 85385"
                        id="Group_85385"
                        transform="translate(15.939 12.848)"
                      >
                        {" "}
                        <path
                          d="M50.175,91.278a3.609,3.609,0,0,0,2.32-.486l14.92-8.033-4.34-4.32Z"
                          data-name="Path 98266"
                          fill="#fff"
                          id="Path_98266"
                          transform="translate(-48.121 -63.156)"
                        ></path>{" "}
                        <path
                          d="M45.033,38.555a3.716,3.716,0,0,0-.587,2.159V62.6A3.756,3.756,0,0,0,45,64.711L58.158,51.618Z"
                          data-name="Path 98267"
                          fill="#fff"
                          id="Path_98267"
                          transform="translate(-44.446 -37.571)"
                        ></path>{" "}
                        <path
                          d="M67.493,44.33,52.6,36.336a3.66,3.66,0,0,0-2.263-.494l12.84,12.78Z"
                          data-name="Path 98268"
                          fill="#fff"
                          id="Path_98268"
                          transform="translate(-48.227 -35.812)"
                        ></path>{" "}
                        <path
                          d="M94.329,71.339l4.308-2.32c2.406-1.3,2.4-3.412,0-4.7L94.3,61.988,89.618,66.65Z"
                          data-name="Path 98269"
                          fill="#fff"
                          id="Path_98269"
                          transform="translate(-73.422 -52.603)"
                        ></path>{" "}
                      </g>{" "}
                    </g>{" "}
                  </svg>
                </a>
                <a
                  className="mx-1"
                  href="https://apps.apple.com/in/app/renewbuy-partners/id1087315070"
                >
                  <svg
                    className="apps-footer"
                    height="54"
                    viewBox="0 0 54 54"
                    width="54"
                    xmlns="http://www.w3.org/2000/svg"
                  >
                    {" "}
                    <g
                      data-name="353490_apple_apple logo_imac_ipad_macbook_icon"
                      id="_353490_apple_apple_logo_imac_ipad_macbook_icon"
                      transform="translate(0.025 0.178)"
                    >
                      {" "}
                      <g
                        data-name="Group 85381"
                        id="Group_85381"
                        transform="translate(-0.025 0.042)"
                      >
                        {" "}
                        <circle
                          cx="27"
                          cy="27"
                          data-name="Ellipse 206"
                          fill="#939598"
                          id="Ellipse_206"
                          opacity="0.1"
                          r="27"
                          transform="translate(0 -0.22)"
                        ></circle>{" "}
                        <path
                          d="M74.975.042v53.78a26.89,26.89,0,1,0,0-53.78Z"
                          data-name="Path 98256"
                          fill="#939598"
                          id="Path_98256"
                          opacity="0.1"
                          transform="translate(-48.085 -0.042)"
                        ></path>{" "}
                        <circle
                          cx="23.48"
                          cy="23.48"
                          data-name="Ellipse 207"
                          fill="#939598"
                          id="Ellipse_207"
                          r="23.48"
                          transform="translate(3.41 3.41)"
                        ></circle>{" "}
                        <path
                          d="M74.975,9.554V56.513a23.48,23.48,0,0,0,0-46.959Z"
                          data-name="Path 98257"
                          fill="#86888a"
                          id="Path_98257"
                          transform="translate(-48.085 -6.144)"
                        ></path>{" "}
                        <g
                          data-name="Group 85380"
                          id="Group_85380"
                          transform="translate(3.41 25.84)"
                        >
                          {" "}
                          <path
                            d="M9.513,72.114c-.015.348-.026.7-.026,1.049a23.479,23.479,0,0,0,23.48,23.48v-2.1A23.477,23.477,0,0,1,9.513,72.114Z"
                            data-name="Path 98258"
                            fill="#86888a"
                            id="Path_98258"
                            transform="translate(-9.487 -72.114)"
                          ></path>{" "}
                          <path
                            d="M98.428,72.114a23.478,23.478,0,0,1-23.453,22.43v2.1a23.479,23.479,0,0,0,23.479-23.48C98.454,72.812,98.443,72.462,98.428,72.114Z"
                            data-name="Path 98259"
                            fill="#797a7d"
                            id="Path_98259"
                            transform="translate(-51.495 -72.114)"
                          ></path>{" "}
                        </g>{" "}
                      </g>{" "}
                      <g
                        data-name="Group 85382"
                        id="Group_85382"
                        transform="translate(14.105 11.937)"
                      >
                        {" "}
                        <path
                          d="M64.9,67.436a21.29,21.29,0,0,1-1.929,3.6c-1.253,1.907-3.019,4.282-5.206,4.3-1.946.018-2.444-1.266-5.084-1.251s-3.189,1.273-5.135,1.256c-2.188-.02-3.862-2.164-5.116-4.071-3.5-5.332-3.87-11.591-1.709-14.917a7.754,7.754,0,0,1,6.237-3.748c2.32,0,3.778,1.271,5.7,1.271,1.86,0,2.993-1.274,5.674-1.274a7.571,7.571,0,0,1,5.7,3.011A6.525,6.525,0,0,0,64.9,67.436Z"
                          data-name="Path 98260"
                          fill="#fff"
                          fill-rule="evenodd"
                          id="Path_98260"
                          transform="translate(-39.385 -45.655)"
                        ></path>{" "}
                        <path
                          d="M78.246,38.038a6.505,6.505,0,0,0,1.445-4.82,7.034,7.034,0,0,0-4.54,2.442,6.039,6.039,0,0,0-1.484,4.7,5.942,5.942,0,0,0,4.578-2.324Z"
                          data-name="Path 98261"
                          fill="#fff"
                          fill-rule="evenodd"
                          id="Path_98261"
                          transform="translate(-61.332 -33.218)"
                        ></path>{" "}
                      </g>{" "}
                    </g>{" "}
                  </svg>
                </a>
              </div>
            </Col>
          </div>
        </Row> */}
        <Row className="px-1 mx-2 mt-3">
          <div className="w-100 disclaimer-container">
            <Col lg="12">
              <div className="disclaimer-text">
                <p>
                  D2C INSURANCE BROKING PVT. LTD (CIN U66030DL2013PTC249265),
                  Principal Place Of Business: 1st Floor, Plot No 94, Sector-
                  32, Gurugram -122001, Haryana; Registered Office: 3rd Floor,
                  AG-26, Shalimar Bagh, New Delhi-110088, India, IRDAI Broking
                  License Code No. DB 571/14, Certificate No. 505, License
                  Category- Direct Broker (Life & General), Valid Till
                  26/11/2026. Product Information Is Solely Based On The
                  Information Received From The Insurers. For More Details On
                  Risk Factors, Associated Terms And Conditions And Exclusions,
                  Please Read The Sales Brochure Carefully Of Respective Insurer
                  Before Concluding A Sale.
                </p>
              </div>
            </Col>
          </div>
        </Row>
        <Row className="px-1 mx-2 mt-3 alter-row">
          <Col lg="12">
            <div className="w-100 d-flex justify-content-between final-section">
              <p>D2C INSURANCE BROKING PVT. LTD.</p>
              <p>© 2022 RenewBuy All Rights Reserved.</p>
            </div>
          </Col>
        </Row>
      </footer>
    </StyledDiv>
  );
};

export default Footer;

const StyledDiv = styled.div`
  .description {
    font-weight: 400;
    -webkit-text-size-adjust: 100%;
    -webkit-tap-highlight-color: transparent;
    letter-spacing: 0.2px;
    --bs-gutter-x: 1.5rem;
    --bs-gutter-y: 0;
    font-size: 12px;
    color: #636363;
    text-transform: none;
    box-sizing: border-box;
    margin-top: 0;
    margin-bottom: 1rem;
    font-family: "Montserrat" !important;
    line-height: 1.75;
    text-align: left;
  }
  .logo-container {
    width: 100%;
    display: flex;
  }
  .logo {
    width: auto;
    height: 81px;
  }
  .follow {
    font-weight: 700;
    color: #636363;
    font-size: 18px;
    text-align: left;
    letter-spacing: 1px;
  }
  .list-container {
    width: 100%;
    text-align: left;
  }
  .ul-style {
    margin-left: 0;
    padding-left: 0;
    margin-bottom: 10px;
  }
  .heading-a-tag {
    line-height: 1.5;
    list-style: none;
    box-sizing: border-box;
    position: relative;
    display: inline-block;
    text-decoration: none;
    background-color: transparent !important;
    font-size: 18px;
    text-transform: capitalize;
    color: #636363;
    padding: 0 0 15px;
    font-weight: 700;
    text-align: left;
  }
  .a-tag {
    -webkit-text-size-adjust: 100%;
    -webkit-tap-highlight-color: transparent;
    --bs-gutter-x: 1.5rem;
    --bs-gutter-y: 0;
    list-style: none;
    box-sizing: border-box;
    text-decoration: none;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out,
      border-color 0.15s ease-in-out;
    position: relative;
    display: inline-block;
    font-size: 11px;
    padding: 0 0 0px;
    color: #636363;
    text-transform: capitalize;
    font-family: "Montserrat";
    text-align: left;
  }
  .list-a {
    color: #636363;
    font-size: 12px;
    text-align: left;
    margin-right: 20px;
    font-weight: 400;
    margin-top: 8px;
  }
  .discover {
    border-top: 1px solid rgba(112, 112, 112, 20%);
    border-bottom: 1px solid rgba(112, 112, 112, 20%);
    padding: 10px 0;
  }
  .discover-text {
    font-weight: 700;
    color: #636363;
    font-size: 18px;
    text-align: left;
    margin-top: 4px;
    margin-right: 10px;
  }
  .apps-footer {
    height: 35px;
    width: auto;
  }
  .disclaimer-text {
    color: #636363;
    font-size: 12px;
    font-weight: 400;
    text-align: left;
  }
  .disclaimer-container {
    border-bottom: 1px solid rgba(112, 112, 112, 20%);
    padding: 10px 0;
    font-weight: 400;
    line-height: 1.75;
  }
  .final-section {
    color: #636363;
    font-size: 12px;
    font-weight: 400;
    padding: 0 0;
  }
  .alter-row {
    margin-bottom: 0;
  }
`;
