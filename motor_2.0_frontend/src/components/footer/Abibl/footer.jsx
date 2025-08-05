import React, { useEffect } from "react";
import { Container } from "react-bootstrap";
import styled, { createGlobalStyle } from "styled-components";
import { useSelector } from "react-redux";

const Footer = () => {
  const { theme_conf } = useSelector((state) => state.home);

  return (
    <>
      <FooterTag>
        <StyledUpperFooter>
          <Container>
            <h5>Aditya Birla Insurance Broker Limited</h5>
            <small style={{ textTransform: "capitalize" }}>
              {` Registered office: indian rayon compound, veraval, gujarat 362266.
              IRDAI license number: 146. composite broker. license valid till:
              9th april 2024. CIN: ${
                theme_conf?.broker_config?.cinnumber
                  ? theme_conf?.broker_config?.cinnumber
                  : "U99999GJ2001PLC062239"
              }. corporate office: one
              world centre, tower-1, 7th floor, jupiter mill compound, 841,
              senapati bapat marg, elphinstone road, mumbai 400 013. tel no.:
              ${
                theme_conf?.broker_config?.phone
                  ? theme_conf?.broker_config?.phone
                  : "+91 22 43568585"
              }.`}
            </small>
            <br />
            <small>
              In case of any queries/complaints/grievances, please write to us
              at{" "}
              <a
                href={`mailto:${
                  theme_conf?.broker_config?.email
                    ? theme_conf?.broker_config?.email
                    : "clientfeedback.abibl@adityabirlacapital.com"
                }`}
                target="_blank"
              >
                $
                {theme_conf?.broker_config?.email
                  ? theme_conf?.broker_config?.email
                  : "clientfeedback.abibl@adityabirlacapital.com"}
              </a>
              . ISO 9001 Quality Management certified by BSI under certificate
              number FS 611893. Aditya Birla Insurance Brokers Limited, Aditya
              Birla Health Insurance Co. Limited and Aditya Birla Sun Life
              Insurance Company Limited are part of the same promoter group.
              Insurance is a subject matter of solicitation.
            </small>
          </Container>
        </StyledUpperFooter>

        <StyledBottomFooter>
          <Container>
            <img
              src={`${
                import.meta.env.VITE_BASENAME !== "NA"
                  ? `/${import.meta.env.VITE_BASENAME}`
                  : ""
              }/assets/images/adityaBirlaLogo.png`}
              width="150px"
              alt="logo"
              style={{ display: "inline" }}
            />
            <br />
            <small>© 2021, Aditya Birla Capital Inc. All Right Reserved</small>
          </Container>
        </StyledBottomFooter>
      </FooterTag>
      <GlobalStyle />
    </>
  );
};

export default Footer;

const StyledUpperFooter = styled.div`
  background: #201e19;
  padding: 10px 0;
  padding-bottom: 20px;
  h5,
  small {
    color: #a1a2a2;
    padding: 10px 0;
  }
  a {
    color: #6c7f85;
    &:hover {
      text-decoration: underline !important;
    }
  }
`;

const StyledBottomFooter = styled.div`
  padding: 30px 0;
  background: #6c7174;
  small {
    color: white;
  }
`;

const FooterTag = styled.footer`
  padding: unset !important;
  text-align: left !important;
`;

const GlobalStyle = createGlobalStyle`
body::after {
    content: '';
    display: block;
    height: 325px; /* Set same as footer's height */
  }

`;
