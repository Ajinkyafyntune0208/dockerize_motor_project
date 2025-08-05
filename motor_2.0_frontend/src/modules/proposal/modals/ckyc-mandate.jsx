import React from "react";
import { Modal } from "react-bootstrap";
import { Button } from "components";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import _ from "lodash";
import styled, { createGlobalStyle } from "styled-components";
import ckyc from "../../../utils/img/ckyc.jpg";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const ckycMandate = (props) => {
  const { theme_conf } = props;
  return (
    <Modal
      {...props}
      size="lg"
      aria-labelledby="contained-modal-title-vcenter"
      centered
      backdrop={"static"}
      keyboard={false}
    >
      <StyleWrap ckyc={ckyc} theme_conf={theme_conf}>
        <Modal.Header>
          <Modal.Title id="contained-modal-title-vcenter">
            {theme_conf?.broker_config?.mandate_title || "Please Note"}
          </Modal.Title>
        </Modal.Header>
        <Modal.Body>
          <Heading4>
            {theme_conf?.broker_config?.mandate_h ||
              `As per IRDA KYC is mandatory for all policies from `}
            {!theme_conf?.broker_config?.mandate_h && (
              <StyedText>1st Jan'23</StyedText>
            )}
          </Heading4>
          <ParaWrapper>
            {theme_conf?.broker_config?.mandate_p1 ? (
              <StyledP>
                <StyedText> * </StyedText>
                {theme_conf?.broker_config?.mandate_p1}
              </StyledP>
            ) : (
              <StyledP>
                <StyedText> * </StyedText>
                <b>Customer name</b> and <b>DOB</b> should match with the
                document used for CKYC like <b>Pan card</b>, <b>Aadhar</b>, etc.
              </StyledP>
            )}
            {theme_conf?.broker_config?.mandate_p2 ? (
              <StyledP>
                <StyedText> * </StyedText>
                {theme_conf?.broker_config?.mandate_p2}
              </StyledP>
            ) : (
              <StyledP>
                <StyedText> * </StyedText> All documents should be clear and
                have same <b>Customer name</b> and <b>DOB</b> which are uploaded
                on insurance portal for CKYC.
              </StyledP>
            )}
          </ParaWrapper>
        </Modal.Body>
        <Modal.Footer>
          <Button
            type="submit"
            buttonStyle="outline-solid"
            className=""
            shadow={"none"}
            hex1={
              Theme?.proposalProceedBtn?.hex1
                ? Theme?.proposalProceedBtn?.hex1
                : "#4ca729"
            }
            hex2={
              Theme?.proposalProceedBtn?.hex2
                ? Theme?.proposalProceedBtn?.hex2
                : "#4ca729"
            }
            borderRadius="5px"
            color="white"
            onClick={props.onHide}
          >
            <text
              style={{
                fontSize: "15px",
                padding: "-20px",
                margin: "-20px -5px -20px -5px",
                fontWeight: "400",
              }}
            >
              I Agree
            </text>
          </Button>
        </Modal.Footer>
        <GlobalStyle />
      </StyleWrap>
    </Modal>
  );
};

export default ckycMandate;

const GlobalStyle = createGlobalStyle`

  .modal {
    z-index: 10000 !important;
  }
`;

const StyleWrap = styled.div`
  .modal-body {
    background-image: ${({ ckyc }) => `url(${ckyc || ""})`};
    background-repeat: no-repeat;

    background-position: ${({ theme_conf }) =>
      theme_conf?.broker_config?.mandate_h &&
      theme_conf?.broker_config?.mandate_h.length > 20
        ? "top 70px right"
        : "right"};
    background-size: 45%;
    /* opacity: 0.5; */
    @media (max-width: 767px) {
      background-image: none;
      background-position: center;
    }
    @media (max-width: 650px) {
      background-image: none;
      background-size: 65%;
    }
    @media (max-width: 500px) {
      background-image: none;
      background-size: 85%;
    }
  }
`;
const Heading4 = styled.h4`
  @media (max-width: 767px) {
    font-size: 19px;
  }
  font-size: ${({ theme_conf }) =>
    theme_conf?.broker_config?.mandate_h.length > 20 ? "13px" : "19px"};
`;
const StyledP = styled.p`
  margin-top: 20px;
  line-height: 30px;
  font-size: 16px;
  @media (max-width: 767px) {
    line-height: 25px;
    margin: 10px 0px 0px 0px;
    font-size: 14px;
  }
`;
const ParaWrapper = styled.div`
  width: 60%;
  padding-bottom: 65px;
  @media (max-width: 767px) {
    width: 100%;
    padding-bottom: 45px;
  }
`;
const StyedText = styled.b`
  color: ${({ theme }) =>
    theme?.proposalProceedBtn?.hex1
      ? theme?.proposalProceedBtn?.hex1
      : "black"};
`;
