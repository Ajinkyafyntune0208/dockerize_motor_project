import React from "react";
import styled from "styled-components";
import { Switch } from "components";

const Consent = ({ selected, setSelected }) => {
  return (
    <Container>
      <Content>
        <Heading>Get updates on SMS / Whatsapp</Heading>
        <SwitchButton>
          <Switch consent value={selected} onChange={setSelected} />
        </SwitchButton>
      </Content>
      <Paragraph>
        <span className="boldText">Disclaimer : </span>
        <span className="text">
          Your information stays between us. We hate spam, too and would never,
          ever share your data with third parties. Proceeding further you agree
          to our Privacy Policy / Terms & Condition.
        </span>
      </Paragraph>
    </Container>
  );
};

export default Consent;

const Container = styled.div`
  max-width: 520px;
  margin: -25px auto auto auto;
`;

const Content = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
`;

const Heading = styled.div`
  font-size: 18px;
  margin: 0 15px;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"Titillium Web", sans-serif`};
  @media (max-width: 767px) {
    font-size: 16px;
  }
`;
const Paragraph = styled.p`
  font-size: 14px;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"Titillium Web", sans-serif`};
  text-align: center;
  padding: 0 15px;
  margin-top: -10px;
  .boldText {
    font-weight: 500;
  }
  .text {
    font-size: 13px;
    color: gray;
  }
`;

const SwitchButton = styled.div`
  margin-bottom: 5px;
  @media (max-width: 767px) {
    position: relative;
    right: 30px;
    bottom: -12px;
  }
`;
