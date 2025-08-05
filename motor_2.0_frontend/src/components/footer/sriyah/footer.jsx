import React from "react";
import { useMediaPredicate } from "react-media-hook";
import {
  FooterContainer,
  TopContainer,
  BottomContainer,
  GlobalStyle,
  FooterTag,
} from "./shiyahFooterStyle";

const Footer = () => {
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const todayDate = new Date();
  return (
    <FooterTag>
      <FooterContainer>
        <TopContainer>
          <div
            className="container"
            // style={{ fontFamily: `'Poppins', sans-serif !important` }}
          >
            <div className="row">
              <div className="col-lg-3 col-md-6">
                <div className="f_widget about-widget pl_40">
                  <h3 className="f-title f_600 f_size_18 mb_40">Insurances</h3>
                  <ul className="list-unstyled f_list">
                    <li>
                      <a href="#">Car Insurance</a>
                    </li>
                    <li>
                      <a
                        target="_blank"
                        href={
                          import.meta.env.VITE_PROD === "YES"
                            ? "https://health.nammacover.com/"
                            : "https://health-uat.nammacover.com/"
                        }
                      >
                        Health Insurance
                      </a>
                    </li>
                    <li>
                      <a href="#">Super Top Up Insurance</a>
                    </li>
                  </ul>
                </div>
              </div>
              <div className="col-lg-3 col-md-6">
                <div className="f_widget about-widget">
                  <h3 className="f-title f_600 f_size_18 mb_40">At a glance</h3>
                  <ul
                    className="list-unstyled f_list"
                    style={{ fontFamily: `'Poppins', sans-serif !important` }}
                  >
                    <li>
                      <a
                        target="_blank"
                        href={
                          import.meta.env.VITE_PROD === "YES"
                            ? "https://nammacover.com/about_us"
                            : "https://uat.nammacover.com/about_us"
                        }
                      >
                        About Us
                      </a>
                    </li>
                    <li>
                      <a
                        target="_blank"
                        href={
                          import.meta.env.VITE_PROD === "YES"
                            ? "https://nammacover.com/terms_of_use"
                            : "https://uat.nammacover.com/terms_of_use"
                        }
                      >
                        Terms Of Use
                      </a>
                    </li>
                    <li>
                      <a
                        target="_blank"
                        href={
                          import.meta.env.VITE_PROD === "YES"
                            ? "https://nammacover.com/grievance_policy"
                            : "https://uat.nammacover.com/grievance_policy"
                        }
                      >
                        Grievance policy
                      </a>
                    </li>
                  </ul>
                </div>
              </div>
              <div className="col-lg-6 col-md-6">
                <div className="f_widget company_widget">
                  <a
                    href={
                      import.meta.env.VITE_PROD === "YES"
                        ? "https://nammacover.com/"
                        : "https://uat.nammacover.com/"
                    }
                    className="f-logo"
                  >
                    <img
                      src={`${
                        import.meta.env.VITE_BASENAME !== "NA"
                          ? `/${import.meta.env.VITE_BASENAME}`
                          : ""
                      }/assets/images/sriyah-footer.png`}
                      className="logo_ic"
                      alt=""
                    />
                  </a>
                  <div className="widget-wrap">
                    <p
                      className="color_grey_f"
                      style={{
                        color: "#1f1f1f",
                        fontSize: "16px",
                        fontFamily: "sans-serif",
                      }}
                    >
                      An initiative of Sriyah Insurance Brokers Pvt. Ltd.
                    </p>
                    <p
                      className="color_grey_f"
                      style={{
                        color: "#1f1f1f",
                        fontSize: "16px",
                        fontFamily: "sans-serif",
                      }}
                    >
                      Sriyah Insurance Brokers Pvt. Ltd. is an Insurance
                      Regulatory &amp; Development Authority of India (IRDAI)
                      approved direct insurance brokers.
                    </p>
                    <p className="f_400 f_p f_size_15 mb-0 l_height34 color_grey_f">
                      <span>Email:</span>
                      <a href="mailto:care@nammacover.com" className="f_400">
                        care@nammacover.com
                      </a>
                    </p>
                    <p className="f_400 f_p f_size_15 mb-0 l_height34 color_grey_f">
                      <span>Phone:</span>
                      <a href="tel:18002030504" className="f_400">
                        1800 203 0504
                      </a>
                    </p>
                  </div>
                </div>
                <div className="f_social_icon" style={{ display: "none" }}>
                  <a href="#" className="ti-facebook">
                    <i className="fab fa-facebook-f"></i>
                  </a>
                  <a href="#" className="ti-twitter-alt">
                    <i className="fab fa-twitter"></i>
                  </a>
                  <a href="#" className="ti-youtube">
                    <i className="fab fa-youtube"></i>
                  </a>
                  <a href="#" className="ti-linkedin">
                    <i className="fab fa-linkedin-in"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </TopContainer>

        <BottomContainer>
          <div className="container">
            <div classname="row align-items-center">
              <div className="col-lg-12 col-md-12 col-sm-12 text-center">
                <p className="mb-0 f_400 color_grey">
                  IRDAI Registration No. 203 | License valid till : 26/08/2027 |
                  Category : Direct Broker | CIN : U66010KA2003PTC031462 <br />
                  Member of Insurance Brokers Association of India (IBAI).
                  Insurance is the subject matter of solicitation. <br />
                  For a seamless experience, use the latest version of Chrome,
                  Firefox or Edge <br />
                  <br /> Copyright {todayDate.getFullYear()} Sriyah Insurance
                  Brokers Pvt. Ltd.
                </p>
              </div>
            </div>
          </div>
        </BottomContainer>
      </FooterContainer>
      <GlobalStyle lessthan767={lessthan767} />
    </FooterTag>
  );
};

export default Footer;
