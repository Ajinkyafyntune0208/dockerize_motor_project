import styled from 'styled-components';
import { Form } from 'react-bootstrap';

export const Container = styled.div`
  .label-text {
    font-family: ${(props) => props?.fonts} !important;
    font-weight: 400;
  }
`;

export const Header = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
`;
export const TraceId = styled.div`
  padding: 2px 5px;
  border-radius: 3px;
`;
export const FilterContainer = styled.div`
  text-align: center;
  box-shadow: rgba(149, 157, 165, 0.2) 0px 8px 24px;
  padding: 25px 0;
`;

export const Filter = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 30px 0;
`;
export const ClearAll = styled.button`
  background-color: ${(props) => props?.primary};
  border-radius: 40px;
  padding: 10px 30px;
  color: #ffffff;
  border: none;
`;
export const Tabs = styled.div`
  border: ${(props) =>
    props?.primary ? `1px solid ${props?.primary}` : "none"};
`;
export const Tab = styled.button`
  padding: 5px;
  background-color: ${(props) => (props?.primary ? props?.primary : "#fff")};
  color: ${(props) => (props?.primary ? "#fff" : props?.primary)};
  border: none;
`;

export const ToggleSwitch = styled(Form.Check)`
  .custom-switch-1 .custom-control-label::after {
    background-color: white;
  }
  .custom-switch .custom-control-label::after {
    background-color: white;
  }
  .custom-control-input:checked ~ .custom-control-label::before {
    background-color: ${(props) => props?.primary};
    border-color: ${(props) => props?.primary};
  }
  .custom-control-input:not(:disabled):active ~ .custom-control-label::before {
    background-color: #fff;
  }
`;

export const ChooseIdv = styled.div`
  border-bottom: ${(props) => `1px solid ${props?.primary}`};
`;
export const MainSection = styled.div`
  display: flex;
  gap: 50px;
`;
export const Addons = styled.div`
  flex: 1;
`;
export const Cards = styled.div`
  flex: 4;
  display: flex;
  justify-content: flex-start;
  gap: 25px;
`;

export const Addon = styled.div``;

export const FilterMenuBoxCheckConatiner = styled.div`
  .filterMenuBoxCheck input[type="checkbox"]:checked + label:before {
    background-color: ${({ theme }) => theme.CheckBox?.color || "#bdd400"};
    border: ${(props) => `1px solid ${props?.primary}`};
    box-shadow: ${({ theme }) =>
      theme.QuoteBorderAndFont?.shadowCheck || "none"};
    filter: ${({ theme }) =>
      theme.QuoteBorderAndFont?.filterPropertyCheckBox || "none"};
  }
`;

export const Item = styled.div`
  padding: 5px;
  margin: 8px 0;
`;
export const LogoImg = styled.div`
  width: 120px;
  height: 55px;
  border: 1px solid;
  border-radius: 10px;
  display: flex;
  justify-content: center;
  align-items: center;
`;

export const QuoteCard = styled.div`
  height: 350px;
  width: 250px;
  box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
  border-radius: 10px;
  padding: 15px;
  position: relative;
`;

export const BuyNowBtn = styled.button`
  border-radius: 30px;
  padding: 5px 25px;
  font-size: 13px;
  border: none;
  background: ${({ primary }) => primary};
  color: #fff;
  font-weight: 600;
`;

export const HowerTabs = styled.div`
  z-index: 997;
  display: flex;
  position: relative;
  bottom: 8px;
  justify-content: center;
  align-items: center;
  .badge-secondary {
    background: white !important;
    cursor: pointer !important;
    color: ${(props) => props?.primary} !important;
  }
  flex-direction: column;
  .arrowIcon {
    color: ${(props) => props?.primary} !important;
    font-size: 12px;
    transition: all 0.3s ease-in-out;
  }
`;

export const AddonItem = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  margin: 12px 0;
`;

export const FoldedRibbon = styled.div`
  --f: 5px; /* control the folded part*/
  --r: 5px; /* control the ribbon shape */
  --t: 5px; /* the top offset */

  position: absolute;
  overflow: visible;
  font-size: 11.5px;
  font-weight: 600;
  color: #fff;
  inset: var(--t) calc(-1 * var(--f)) auto auto;
  padding: 0 10px var(--f) calc(10px + var(--r));
  clip-path: polygon(
    0 0,
    100% 0,
    100% calc(100% - var(--f)),
    calc(100% - var(--f)) 100%,
    calc(100% - var(--f)) calc(100% - var(--f)),
    0 calc(100% - var(--f)),
    var(--r) calc(50% - var(--f) / 2)
  );
  background: ${(props) => props?.primary || "#4ca729"};
  box-shadow: 0 calc(-1 * var(--f)) 0 inset #0005;
  /* z-index: 999 !important; */
`;

export const Buttons = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  margin-top: 20px;
  color: ${(props) => props?.primary || "#4ca729"};
`;
